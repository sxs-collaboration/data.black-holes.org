The nginx container protects requests to certain URLs by authenticating the user with a subrequest.  In our case, any location with the line `auth_request /auth/check;` sends a request to the `oauth2` container along with any cookies and headers the user sent along with the original request.  Normally, this will include a `session` cookie containing an oauth token from github.

The `oauth2` container uses three "secrets" files, which should be located in the `../secrets`
directory (relative to the directory this file is in).  These files are listed at the bottom of the relevant [`docker-compose.yml`](https://github.com/sxs-collaboration/black-holes.org/blob/master/www/docker-compose.yml#L146) file.  They include the `oauth2_proxy_client_id` and `oauth2_proxy_client_secret` files, which should contain precisely the Client ID and Client Secret listed on [the OAuth App page](https://github.com/organizations/sxs-collaboration/settings/applications/584514) (no  comments, newlines, etc.).  The third file should be `oauth2_proxy_cookie_secret`, which simply contains some random string.  An easy way to generate this string is with this command:

```bash
python -c 'import os; from base64 import b64encode; print(b64encode(os.urandom(24)).decode("utf-8"))'
```

Once those files are in place, the oauth2 app can start.  It is a simple [flask](http://flask.pocoo.org/) app, the key file for which is [`app.py`](https://github.com/sxs-collaboration/black-holes.org/blob/master/www/oauth2/www/app.py).  In particular, note the `check_user_auth_for_path` function, which checks that the user is in either the `sxs-collaboration` group or one of various teams on github.  If that function returns `True`, the app returns a 200 status code from the subrequest nginx made originally, and the user is allowed to pass through nginx to the originally requested page.  (If not, a 403 request is returned, which nginx returns to the user via the `/auth/login` page.)  The app also returns several headers available to nginx as `$upstream_http_x_auth_request_user`, etc.  These can be copied as headers sent to the original request's destination, like dokuwiki for example.

