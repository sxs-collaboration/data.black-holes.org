#! /bin/bash

set -e

# Make sure that the cron.d directory is owned by root and unreadable by anyone else
chown -R 0:0 /web/servers/cron/cron.d  # 0:0 is root:root
chmod -R go-rwx /web/servers/cron/cron.d

# Ensure a place for the letsencrypt log file
mkdir -p /var/log/letsencrypt

# Deal with buggy container
touch /etc/crontab
touch /etc/mailname
# mkfifo /var/spool/postfix/public/pickup  # This bug has apparently been fixed

# Connect the log files to docker's output
ln -sf /proc/$$/fd/1 /var/log/cron.log
ln -sf /proc/$$/fd/1 /var/log/mail.log
ln -sf /proc/$$/fd/1 /var/log/letsencrypt/letsencrypt.log

# For some reason, we have to start postfix and rsyslog ourselves
service postfix start
service rsyslog start

# Make extra sure that we're up to date with the sxs module
pip install --force-reinstall --ignore-installed --upgrade --no-cache-dir sxs

# Start cron running in the foreground, with highest loglevel
cron -f -L 15
