"""OAuth handler for nginx

Documentation:
    https://www.nginx.com/resources/admin-guide/restricting-access-auth-request/
    http://nginx.org/en/docs/http/ngx_http_auth_request_module.html

Nginx only accepts HTTP responses in the 200 range, or 401, or 403. Anything else is considered an
internal error (in this app), and the user will see a 500 response.  HOWEVER, uwsgi does not support
401 responses (when connecting over uwsgi, as opposed to http or fastCGI), because it closes the
connection after every request, and thus gets mapped to 404.  Therefore, only a 2xx or a 403
response is permissible here unless we communicate over http or fastCGI, and then you seem to need
some `keepalive` option to uwsgi.

"""

import time
import os
from base64 import b64encode
import urllib.parse
import json
from requests_oauthlib import OAuth2Session
from flask import Flask, request, redirect, session, url_for, Response, render_template, escape
from flask.json import jsonify

os.environ['OAUTHLIB_INSECURE_TRANSPORT'] = '1'

app = Flask(__name__)
app.config['SESSION_COOKIE_SECURE'] = True

# This information is obtained upon registration of a new GitHub OAuth app:
#   https://github.com/settings/applications/new
with open('/run/secrets/client_id', 'r') as f:
    client_id = f.read().strip()
with open('/run/secrets/client_secret', 'r') as f:
    client_secret = f.read().strip()
with open('/run/secrets/cookie_secret', 'r') as f:
    app.secret_key = f.read().strip()

authorization_base_url = 'https://github.com/login/oauth/authorize'
token_url = 'https://github.com/login/oauth/access_token'


def check_user_auth_for_path(user, orgs_and_teams, path):
    """Customizable function for determining if the given user can see the given URL path

    If this function returns True (or anything that converts to True as a boolean), access is
    allowed; otherwise access is forbidden.

    Parameters
    ----------
    user: string
        The user's github login name.
    orgs_and_teams: list of strings
        A list of the 'login' strings for each github organization the user belongs to (and
        authorizes this app), as well as 'login:name' strings for each team named 'name' within an
        organization with login 'login'.  For example, if the user is on the `SpEC` team inside the
        `sxs-collaboration`, this list will contain `sxs-collaboration:SpEC`.
    path: string
        The requested URL component, specifically the part after the scheme (e.g., https) and the
        host (e.g., www.black-holes.org), and including the initial '/'.  For example, a request to
        https://www.black-holes.org/private/goodies will have path '/private/goodies'.  Note that
        this also includes any query string (a question mark and whatever follows it) that might
        have been sent in the URL.

    """

    # # DEBUG; see the result of this statement with `docker-compose logs oauth2`
    # print(user, orgs_and_teams, path)

    # Allow anyone in the github organization 'sxs-collaboration' to see anything
    if 'sxs-collaboration' in orgs_and_teams:
        return True

    if 'sxs-collaboration:SpEC' in orgs_and_teams:
        return True

    if 'sxs-collaboration:spectre' in orgs_and_teams:
        return True

    for ot in orgs_and_teams:
        if ot.startswith('sxs-collaboration:wiki'):
            return True

    return False


@app.route("/auth/check")
def check():
    """Step 0: Check for authorization

    This route is called from nginx to check if the user is authorized to access the requested page.
    As explained in the nginx documentation for the auth_request module
        <http://nginx.org/en/docs/http/ngx_http_auth_request_module.html>,
    if nginx's request to this route returns a 2xx response code, the access is allowed.  If a 401
    or 403 code is returned, access is denied.  Any other code is considered an error, and nginx
    will return 500 to the client.  Note that proxied uwsgi requests do not support 401, as they
    require an HTTP keepalive, which is not possible because uwsgi closes all connections
    immediately on response.

    """
    response = Response('Forbidden', 403)

    # Make sure we've stored everything we need.  If not, nginx will redirect to /auth/github.
    if not 'oauth_token' in session:
        print('No oauth_token in session.  Replying with 403.  ' + repr(session))
        response.headers['X-Auth-Failure'] = 'no_oauth_token'
        return response

    # Now check with github to make sure we're authorized
    github = OAuth2Session(client_id, token=session['oauth_token'])
    check_validity_every = 60*60  # seconds
    if not github.authorized or seconds_since_last_github_check() > check_validity_every:
        print('Checking oauth token for {0} with github.'.format(session.get('github_login', 'unknown user')))
        if not github.get('https://api.github.com/user').ok:
            # The `get` call makes a request to github, which has come back as not OK, which means
            # that the token we provided with that call was not accepted.
            response.headers['X-Auth-Failure'] = 'github_rejected_user'
            return response
        session['github_last_check'] = time.time()
        print('Oauth token checked out valid on {0}.'.format(session['github_last_check']))

    # Ensure that the crucial pieces of information are available
    if not ('github_login' in session and 'github_orgs_and_teams' in session):
        print('User info missing from session; updating now.')
        refresh(github)
        if not ('github_login' in session and 'github_orgs_and_teams' in session):
            print('Could not get user info.  Replying with 403.')
            response.headers['X-Auth-Failure'] = 'github_rejected_details'
            return response

    # Get the requested URL
    redirects = request.headers.getlist('X-Auth-Request-Redirect')
    if redirects:
        path = redirects[0]
    else:
        path = '/'

    # Run our function for allowing access to a given URL for a given user
    if not check_user_auth_for_path(session['github_login'], session['github_orgs_and_teams'].split('|'), path):
        if seconds_since_last_github_check() > 10:
            # Try to update the information, in case team membership has changed, etc.
            refresh(github)
            if not check_user_auth_for_path(session['github_login'], session['github_orgs_and_teams'].split('|'), path):
                response.headers['X-Auth-Failure'] = 'unauthorized_' + session['github_login']
                return response
        else:
            response.headers['X-Auth-Failure'] = 'unauthorized_' + session['github_login']
            return response

    response = Response('OK', 200)
    response.headers['X-Auth-Request-User'] = session.get('github_login', '')
    response.headers['X-Auth-Request-Name'] = session.get('github_name', '')
    response.headers['X-Auth-Request-Email'] = session.get('github_email', '')
    response.headers['X-Auth-Request-Groups'] = session.get('github_orgs_and_teams', '')
    return response


@app.route("/auth/login")
def login():
    """Step 0.5: Explain what has to happen now, if the authorization failed.

    If /auth/check failed, it will set a header X-Auth-Failure.  (We can't just pass this
    information via the session cookie, because failure to set the cookie is one reason the check
    might have failed.)  This function examines that header and constructs a message to explain what
    is needed for the login to proceed.

    1) Session cookie has no 'test' field, so cookies need to be enabled
    2) Github callback returned with an error
    3) session['oauth_state'] was not present, so either /auth/github got called directly, or it could not be set
    4) session['oauth_token'] was not present, so either /auth/check got called before login, or it could not be set
    5) could not get user from github, so the oauth token supplied was not accepted (maybe the app was disabled by the user)
    6) could not get user login and orgs and team from github, so user may have de-scoped the token
    7) the user simply is not authorized

    """
    failure_reason = request.headers.getlist('X-Auth-Failure')
    if len(failure_reason) > 0:
        failure_reason = failure_reason[0]
    else:
        failure_reason = ''

    if failure_reason == 'no_session':
        message = "Unable to set session cookie.  Please ensure that cookies can be set by this site and by github.com."
    elif failure_reason == 'github_error':
        message = ("Github OAuth returned an error.  Please ensure that cookies can be set by this site and by github.com.  "
                   + "Also ensure that the black-holes.org OAuth app is authorized on "
                   + "<a href='https://github.com/settings/connections/applications/ee6f483db600575707ad'>your user page</a> "
                   + "and is allowed to read org and team membership and user email addresses.")
    elif failure_reason == 'no_oauth_state':
        message = "Unable to set OAuth state in session cookie.  Please ensure that cookies can be set by this site and by github.com."
    elif failure_reason == 'no_oauth_token':
        message = "No OAuth token in session cookie.  Please ensure that cookies can be set by this site and by github.com."
    elif failure_reason == 'github_rejected_user':
        message = ("Could not get user information from github.  "
                   + "Please ensure that the black-holes.org OAuth app is authorized on "
                   + "<a href='https://github.com/settings/connections/applications/ee6f483db600575707ad'>your user page</a> "
                   + "and is allowed to read org and team membership and user email addresses.")
    elif failure_reason == 'github_rejected_details':
        message = ("Could not get user information, organizations, and teams from github.  "
                   + "Please ensure that the black-holes.org OAuth app is authorized on "
                   + "<a href='https://github.com/settings/connections/applications/ee6f483db600575707ad'>your user page</a> "
                   + "and is allowed to read org and team membership and user email addresses.")
    elif failure_reason.startswith('unauthorized_'):
        user_id = escape(failure_reason[13:])
        message = ("You are authenticated via github as user '{0}', but do not have sufficient permissions ".format(user_id)
                   + "to access this page.  If you feel that this is an error, please email the sxs-collaboration admins with the "
                   + "URL of the page you were trying to access and a list of "
                   + "<a href='https://github.com/settings/organizations'>the oganizations and teams you belong to</a> on github.  "
                   + "Alternatively, if you have a password, feel free to try that instead.")
    else:
        message = "An unknown error occurred while trying to log in.  Please try again."

    return render_template('login.html', message=message)


@app.route('/auth/github')
def github():
    """Step 1: Redirect to github user authorization

    Redirect the user/resource owner to the OAuth provider (Github) using a URL with a few key OAuth
    parameters.

    """
    github = OAuth2Session(client_id, scope='user:email,read:org')
    authorization_url, state = github.authorization_url(authorization_base_url)

    # The state is used to prevent cross-site request forgery (CSRF).  It gets checked in the
    # callback.
    session['oauth_state'] = state

    # We save the URL that was actually requested, so that we know where to send the user after they
    # authorize us on github and go through the callback.  For some reason, request insists on
    # returning a list of headers; if we try to just get "the" header with this name, we get None ---
    # even though there's exactly one --- so we need to get a list of that one header.
    if 'redirect' in request.args:
        session['redirect'] = request.args['redirect']
    else:
        redirects = request.headers.getlist('X-Auth-Request-Redirect')
        if redirects:
            url = redirects[0]
            if url != '/auth/github':
                session['redirect'] = url

    # But for now, we send them off to github
    return redirect(authorization_url)


#
# Step 2: User authorization, this happens on github
#


@app.route('/auth/callback')
def callback():
    """Step 3: Retrieving an access token from github

    The user has been redirected back from the provider to the callback URL we registered with
    github. With this redirection comes an authorization code included in the redirect URL. We will
    use that to obtain an access token.

    """
    # First, check to see if github sent back an error
    if 'error' in request.args:
        troubleshooting = 'https://developer.github.com/apps/building-integrations/managing-oauth-apps/troubleshooting-authorization-request-errors/'
        print('Github oauth responded with an error:\n'
              + '\t{0}: {1}\n'.format(request.args.get('error', 'error'), request.args.get('error_description', ''))
              + '\tSee <{0}> for details.\n'.format(request.args.get('error_uri', troubleshooting)))
        response = Response('Forbidden', 403)
        response.headers['X-Auth-Failure'] = 'github_error'
        return response

    # Now, make sure we have the state saved in step 1.
    if 'oauth_state' not in session:
        print('Failed to save oauth state in `session`.  Replying with 403.')
        response = Response('Forbidden', 403)
        response.headers['X-Auth-Failure'] = 'no_oauth_state'
        return response

    # We now send our own request to github, along with our secret.  If this succeeds, we will get
    # back an access token that will allow us to access the user's data on github (within the scope
    # requested in step 1).
    github = OAuth2Session(client_id, state=session.pop('oauth_state'))
    session['oauth_token'] = github.fetch_token(token_url, client_secret=client_secret, authorization_response=request.url)

    # Now we use that token to go and get the list of organizations and teams that the user belongs
    # to.  These lists may not be *all* the orgs and teams the user belongs to; our app must be
    # authorized for those orgs and teams.  By default, new orgs and teams on github are open to all
    # apps, but it is possible to restrict this.  However, any app owned by an organization will be
    # authorized automatically, so as long as we're just checking membership in our own orgs and
    # teams, this won't be a problem.
    github = OAuth2Session(client_id, token=session['oauth_token'])
    refresh(github)

    # Finally, we send the user back to the page they were originally looking for.  Nginx will try
    # again at step 0, but this should succeed now, so the page will get served.
    return redirect(session.pop('redirect', '/'))


@app.route('/auth/refresh')
def refresh(github=None):
    """Helper function to update username, orgs, teams, last check time"""
    try:
        if github is None:
            github = OAuth2Session(client_id, token=session['oauth_token'])
        user = github.get('https://api.github.com/user').json()
        session['github_login'] = user.get('login', '')
        session['github_name'] = user.get('name', '')
        emails = github.get('https://api.github.com/user/emails').json()
        if emails:
            verified_email = [email['email'] for email in emails if 'email' in email and email.get('primary', False)]
            if verified_email:
                session['github_email'] = verified_email[0]
            else:
                session['github_email'] = emails[0]
        else:
            session['github_email'] = ''
        orgs = github.get('https://api.github.com/user/orgs').json()
        teams = github.get('https://api.github.com/user/teams').json()
        session['github_orgs_and_teams'] = '|'.join(
            [org['login'] for org in orgs if 'login' in org]
            +
            ['{0}:{1}'.format(team['organization']['login'], team['name'])
             for team in teams if 'name' in team and 'organization' in team and 'login' in team['organization']]
        )
        session['github_last_check'] = time.time()
        return """<!DOCTYPE html>
        <html>
          <head>
            <meta charset="UTF-8">
            <script>(function(){window.history.go(-1); return false;}());</script>
          </head>
          <body>
            OAuth information refreshed.
          </body>
        </html>""", 200
    except Exception as e:
        url = url_for('.github') + '?redirect=' + urllib.parse.quote_plus('/auth/info')
        return redirect(url)


@app.route('/auth/logout')
def logout():
    session.clear()
    return """<!DOCTYPE html>
    <html>
      <head>
        <meta charset="UTF-8">
        <script>(function(){window.location="https://www.black-holes.org/"; return false;}());</script>
      </head>
      <body>
        You are now logged out.
      </body>
    </html>""", 200


def seconds_since_last_github_check():
    last_check = session.get('github_last_check', 0.0)
    return time.time() - last_check


if __name__ == '__main__':
    # This actually doesn't matter if we're using wsgi; it should never be called.  But in case it
    # is called, we lock it down.
    app.run(
        host='127.0.0.1', # Listen only to localhost
        debug=False,  # Debugging allows execution by remote hosts, so it's a security threat
    )
