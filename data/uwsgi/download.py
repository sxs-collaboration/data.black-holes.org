import http.client
import json
import os
import os.path
import urllib.parse
import subprocess

google_recaptcha_secret = os.environ['GOOGLE_RECAPTCHA_SECRET']

file_extension_whitelist = ['.h5', '.txt', '.out', '.perl', '.tgz']

max_tar_file_size = 1024**3


def check_recaptcha_success(secret, response, remote_ip):
    conn = http.client.HTTPSConnection("www.google.com")
    conn.request("POST", "/recaptcha/api/siteverify?secret={0}&response={1}&ip={2}".format(secret, response, remote_ip))
    response = conn.getresponse()
    data = response.read()
    conn.close()
    success = json.loads(data)['success']
    return success


def application(environ, start_response):
    permitted_files = []
    permitted_file_sizes = []
    try:
        document_root = '/var/www/html'  # *NOT* environ['DOCUMENT_ROOT'] because that's on a different container
        success = True

        # Get the scheme, netloc, and path parts of the URI, dropping any parameters, query, or fragment
        uri_list = list(urllib.parse.urlparse(environ.get('HTTP_REFERER', environ.get('REQUEST_URI', ''))))
        for i in range(3, 6):
            uri_list[i] = ''
        uri = urllib.parse.urlunparse(uri_list)
        error_redirect = uri + '?'  # set value here in case we get immediate errors

        # Get the actual request parameters
        if environ.get('REQUEST_METHOD', 'GET') == 'POST':
            try:
                request_body_size = int(environ.get('CONTENT_LENGTH', 0))
            except (ValueError):
                request_body_size = 0
            request_body = environ['wsgi.input'].read(request_body_size)
            parameters = urllib.parse.parse_qs(request_body)
            if hasattr(request_body, 'decode'):
                request_body = request_body.decode()
            if request_body:
                request_body = request_body + '&'
            error_redirect = uri + '?' + request_body + 'error=true'
        else:
            query_string = environ.get('QUERY_STRING', '')
            parameters = urllib.parse.parse_qs(query_string)
            if hasattr(query_string, 'decode'):
                query_string = query_string.decode()
            if query_string:
                query_string = query_string + '&'
            error_redirect = uri + '?' + query_string + 'error=true'

        # Check that we get all the parameters we need
        if not b'g-recaptcha-response' in parameters:
            success = False
            error_redirect += '&reason=no-captcha'
        if not b'catalog' in parameters:
            success = False
            error_redirect += '&reason=no-catalog'
        if not b'id' in parameters:
            success = False
            error_redirect += '&reason=no-id'
        if not b'path' in parameters:
            success = False
            error_redirect += '&reason=no-path'
        else:
            requested_files = [path.decode('utf-8') for path in parameters[b'path'] if path]
            if len(requested_files) < 1:
                success = False
                error_redirect += '&reason=no-path-items'

        # Check that the captcha succeeded
        if success:
            recaptcha_response = parameters[b'g-recaptcha-response'][0].decode('utf-8')
            remote_ip = environ['REMOTE_ADDR']
            success = check_recaptcha_success(google_recaptcha_secret, recaptcha_response, remote_ip)
            if not success:
                error_redirect += '&reason=bad-captcha'

        # Get this data's JSON file
        if success:
            catalog = parameters[b'catalog'][0].decode('utf-8')
            group_id = parameters[b'id'][0].decode('utf-8')
            catalog_root = os.path.join(document_root, catalog)
            json_path = os.path.join(catalog_root, 'data', group_id+'.json')
            try:
                with open(json_path, 'r') as f:
                    json_data = json.load(f)
            except FileNotFoundError:
                success = False
                print("Could not find json_data in", json_path)
                error_redirect += '&reason=json-file-not-found'

        # Validate the requested paths
        if success:
            # print('json_data:', json_data)
            # print('permitted_files:', permitted_files)
            def recurse(json_dict, permitted_files, permitted_file_sizes):
                file_type = json_dict.get('type', 'illegal_type')
                current_file = json_dict.get('path', '')
                current_file_size = int(json_dict.get('size', 10000000000))
                # print("file_type", file_type)
                # print("current_file:", current_file, file_type in file_extension_whitelist, current_file in requested_files)
                if file_type in file_extension_whitelist and current_file in requested_files:
                    current_path = os.path.join(catalog_root, 'files', current_file)
                    # print("current_path:", current_path, os.path.isfile(current_path))
                    if os.path.isfile(current_path):
                        permitted_files.append(current_file)
                        permitted_file_sizes.append(current_file_size)
                children = json_dict.get('children', [])
                for child in children:
                    recurse(child, permitted_files, permitted_file_sizes)
            recurse(json_data, permitted_files, permitted_file_sizes)
            # print(permitted_files, permitted_file_sizes)

            if not permitted_files:
                success = False
                error_redirect += '&reason=no-permitted-files'

            if len(permitted_files)>1 and sum(permitted_file_sizes) > max_tar_file_size:
                success = False
                error_redirect += '&reason=file_size_{0}'.format(sum(permitted_file_sizes))

    except Exception as e:
        print("Unkown exception:  " + str(e))
        success = False
        error_redirect += '&reason=unknown-exception'

    # Reply if anything went wrong.  Because of the 'error=true' parameter, nginx sends the 404 page
    # without changing the URL in the browser.  This can then be sent as the error, and should
    # contain much of the required information.
    if not success:
        start_response('307 Temporary Redirect', [('Location', error_redirect)])
        return [1]

    if len(permitted_files) == 1:
        uri = '/direct_download/{0}/files/{1}'.format(catalog, permitted_files[0])
        print('Redirecting request for {0} ({1}B) to X-Accel: {2}'.format(permitted_files[0], permitted_file_sizes[0], uri))
        start_response('200 OK',
                       [('X-Accel-Redirect', uri),
                        ('Content-Disposition', 'attachment; filename="{0}"'.format(permitted_files[0]))])
        return [1]

    # If everything went right, tar the files up together and respond
    start_response('200 OK',
                   [('Content-Type', 'application/gzip'),
                    ('Content-Encoding', 'gzip'),
                    ('Content-Disposition', 'attachment; filename="{0}.tar.gz"'.format(group_id))])
    command = ['tar', '--directory={}'.format(os.path.join(catalog_root, 'files')), '--create', '--dereference', '--gzip']
    proc = subprocess.Popen(command + permitted_files, stdout=subprocess.PIPE, bufsize=-1)
    return proc.stdout

    # # NOTE: proc.communicate() waits for the process to end before it returns.  This means that
    # # there's a substantial delay before the download starts (for big files), and the process will
    # # still run to completion if the download is canceled.  Thus, rather than using
    # # proc.communicate() and return the output, a better way is to just return the stdout itself.
    # out, err = proc.communicate()
    # return out
