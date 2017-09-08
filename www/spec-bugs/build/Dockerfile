FROM python:2.7

RUN pip install --no-cache-dir trac

# # Patch trac's htpasswd-reading function, until they merge my pull request:
# #     https://github.com/edgewall/trac/pull/7
# COPY trac_web_auth_py.patchfile /
# RUN patch -p1 -i trac_web_auth_py.patchfile

CMD ["/usr/local/bin/python2", "/usr/local/bin/tracd", \
     "--port", "80", "--auto-reload", \
     "-s", "/var/www/spec-bugs", \
     "--base-path=spec-bugs", \
     "--basic-auth=spec-bugs,/var/www/htpasswd,Restricted"]
