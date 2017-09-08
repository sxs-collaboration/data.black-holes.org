FROM python

# This is the basic item we need to install
RUN pip install --no-cache-dir --upgrade uwsgi Flask requests_oauthlib

# Get the timezone right
ENV TZ=America/Los_Angeles
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# ENTRYPOINT ["python"]
# CMD ["/usr/src/app/app.py"]

CMD ["uwsgi", "--chdir", "/var/www", "--ini", "/var/www/wsgi.ini"]
