#! /bin/bash

email_address="web-admin@black-holes.org"  # Email address for important account notifications
fqdn="black-holes.org"  # Fully qualified domain name

already_have_certificates=$(certbot certificates 2>/dev/null | grep -q "No certs found." ; echo $?)

if [ "$already_have_certificates" -eq "1" ]; then
    # Just renew the existing certs
    certbot renew --quiet
else
    # Otherwise, we have to get the certs first
    certbot certonly --webroot --email $email_address --agree-tos -w /var/www/letsencrypt -d $fqdn
fi
