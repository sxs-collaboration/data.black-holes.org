FROM python:latest

RUN pip install --no-cache-dir uwsgi

CMD ["uwsgi", "--chdir", "/var/www", "--ini", "/var/www/wsgi.ini"]
