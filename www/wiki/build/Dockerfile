FROM php:apache

# Install sendmail and ImageMagick convert
RUN apt-get update \
    && apt-get install -y sendmail imagemagick --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

COPY ./docker-entrypoint.sh /
ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["apache2-foreground"]
