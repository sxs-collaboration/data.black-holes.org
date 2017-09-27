FROM php:7-fpm

RUN sed -i 's/;log_level = .*/log_level = debug\nemergency_restart_threshold = 3\nemergency_restart_interval = 1m\nprocess_control_timeout = 10s/' /usr/local/etc/php-fpm.conf
RUN printf '\n\npm.max_requests = 500\nping.path = /ping\npm.status_path = /status\naccess.format = "%%R - %%u %%t \"%%m %%r%%Q%%q\" %%s %%f %%{mili}d %%{kilo}M %%C%%%%"\n' >> /usr/local/etc/php-fpm.d/www.conf
