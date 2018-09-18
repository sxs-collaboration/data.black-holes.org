FROM php:7-fpm

RUN apt-get update \
    && echo "postfix postfix/mailname string black-holes.org" | debconf-set-selections \
    && echo "postfix postfix/main_mailer_type string 'Internet Site'" | debconf-set-selections \
    && DEBIAN_FRONTEND=noninteractive DEBIAN_PRIORITY=critical \
       apt-get install -y --no-install-recommends postfix mailutils \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
