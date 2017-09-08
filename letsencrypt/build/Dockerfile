FROM debian:jessie-backports

RUN apt-get update \
  && apt-get install -y certbot -t jessie-backports \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/* \
  && mkdir -p /etc/letsencrypt/live/black-holes.org \
  && mkdir -p /etc/letsencrypt/live/www.black-holes.org

ENTRYPOINT [ "certbot" ]
