FROM php:apache

# Install sendmail and ImageMagick convert
RUN apt-get update \
    && apt-get install -y sendmail imagemagick --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

RUN groupadd -g 1002 web
RUN useradd -u 503 -g 1002 -s /bin/bash -G 33 web

COPY ./docker-entrypoint.sh /
ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["apache2-foreground"]
