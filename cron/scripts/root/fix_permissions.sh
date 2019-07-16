#! /bin/sh

# DO NOT set -e here; we want as many as possible to succeed

# We need to explicitly exclude the subdirectories that will be
# getting different permissions, so that if other jobs run with
# non-web permissions while this script is executing, they won't get
# "bad permissions" errors.

find /web/servers \
    \( -type f -o -type d \) \
    -not -path "/web/servers/www/joomladb" \
    -not -path "/web/servers/www/wiki" \
    -not -path "/web/servers/cron/scripts/git" \
    -not -path "/web/servers/home_directories/git" \
    -not -path "/web/servers/secrets/git" \
    -not -path "/web/servers/letsencrypt/certs" \
    -not -path "/web/servers/cron/cron.d" \
    -exec chown 503:33 {} \;
#chown -R 503:33 /web/servers  # web:www-data
chown -R 503:33 /web/servers/www/wiki/document_root/data/media/website/data/waveforms/  # web:www-data
chown -R 999:999 /web/servers/www/joomladb  # mysql:mysql (on joomladb container)
chown -R 33:33 /web/servers/www/wiki/document_root # www-data:www-data
chown -R 522:1003 /web/servers/cron/scripts/git  # git:git
chown -R 522:1003 /web/servers/home_directories/git  # git:git
chown -R 522:1003 /web/servers/secrets/git  # git:git
chown -R 0:0 /web/servers/letsencrypt/certs  # root:root
chown -R 0:0 /web/servers/cron/cron.d  # root:root


find /web/servers \
    \( -type f -o -type d \) \
    -not -path "/web/servers/cron/cron.d" \
    -exec chmod g=u {} \;
chmod -R go-rwx /web/servers/cron/cron.d

# Some times, people commit with bad permissions; this is easier than correcting the repo
find /sxs-annex/SimulationAnnex.git \
    \( -type f -o -type d \) \
    -path "/sxs-annex/SimulationAnnex.git/.git" -prune \
    -o -not -perm -664 -exec chmod u+rw,g+rw,o+r {} +
