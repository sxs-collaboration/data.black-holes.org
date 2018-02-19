FROM debian:latest

# Install a few necessaries
RUN apt-get update \
    && echo "postfix postfix/mailname string black-holes.org" | debconf-set-selections \
    && echo "postfix postfix/main_mailer_type string 'Internet Site'" | debconf-set-selections \
    && DEBIAN_FRONTEND=noninteractive DEBIAN_PRIORITY=critical \
       apt-get install -y --no-install-recommends \
          `### These programs are needed for basic cron functionality ###` \
          cron  `# Basic reason for this container` \
          postfix  `# Allow cron to email about errors` \
          rsyslog  `# I know of no other way to get cron logs` \
          `### The following programs are needed for particular things we want cron jobs to do ###` \
          certbot  `# To update letsencrypt SSL certificates` \
          rsync `# To update files by direct copy` \
          ssh `# To git via ssh` \
          git `# To update other files` \
          git-annex `# When we want to ensure the web server has all annexed files` \
          python-pip `# To install the sxs module` \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Add web and git users and groups
RUN groupadd -g 1003 git
RUN groupadd -g 1002 web
RUN useradd -u 522 -g 1003 -s /bin/bash -G 1002,33 git
RUN useradd -u 503 -g 1002 -s /bin/bash -G 1003,33 web

# Enable cron logging
RUN echo "cron.* /var/log/cron.log" >> /etc/rsyslog.conf

# Get the timezone right
ENV TZ=America/Los_Angeles
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Install the SXS python module (Do this late in the build because this is the part most likely to need changing)
RUN pip install --no-cache-dir --upgrade sxs   # 2018-02-16

# The entrypoint is the script that will run at startup
COPY ./entrypoint.sh /
ENTRYPOINT ["/entrypoint.sh"]
