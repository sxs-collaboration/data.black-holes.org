#! /bin/bash

# This is called as the php:apache entrypoint
bash docker-php-entrypoint

# Enable sendmail and restart the service
echo "127.0.0.1 black-holes.org $(hostname)" >> /etc/hosts
service sendmail start

# Tell PHP how to find sendmail
echo 'sendmail_path = "/usr/sbin/sendmail -t -i"' > /usr/local/etc/php/conf.d/mail.ini

# Enable the apache rewrite module
a2enmod rewrite

# Set the server's fully qualified domain name
echo 'ServerName www.black-holes.org' >> /etc/apache2/apache2.conf

# Start the server
apache2-foreground
