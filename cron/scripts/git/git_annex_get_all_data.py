#! /usr/bin/env python

from __future__ import print_function
import sys
import subprocess

email_template = r"""Hi {first_name},
A data file that you committed to the SimulationAnnex is missing from the webserver.
It seems that you git committed the file, but did not `git annex copy` the file over.
Please do so as soon as possible, so that other people will be able to see the data.
If this is not possible, you may wish to remove the file reference from the git repo,
at least temporarily.  Refer to the documentation on the wiki for further details:

    https://wiki.black-holes.org/documentation/simulation_annex

For your reference, the error I found when trying to get the data is included below.

Yours truly,
T. Webserver

=====================================================================================

{error}
"""


file_names = sys.argv[1:]  # First argv is always script filename

committer_and_errors = {}

for file_name in file_names:
    # print('Trying', file_name)
    try:
        subprocess.check_output(['git', 'annex', 'get', file_name], stderr=subprocess.STDOUT)
    except subprocess.CalledProcessError as e:
        # print('Failed to git annex get', file_name)
        # print(e.output.decode())
        try:
            committer = subprocess.check_output(['git', 'log', '--format=%aN,%aE', file_name], stderr=subprocess.STDOUT)
            if committer in committer_and_errors:
                committer_and_errors[committer] += e.output.decode()
            else:
                committer_and_errors[committer] = e.output.decode()
        except subprocess.CalledProcessError as e2:
            print('='*90)
            print('Unkown committer for file', file_name)
            print(e.output.decode())
            print('='*90)
            print(e2.output.decode())
            print('='*90)


if committer_and_errors:
    import smtplib
    from email.mime.text import MIMEText
    for committer in committer_and_errors.keys():
        name, email = committer.split(',')[:2]
        name, email = name.strip(), email.strip()
        first_name = name.split(' ')[0]
        msg = email_template.format(first_name=first_name, error=committer_and_errors[committer])
        msg = MIMEText(msg)
        msg['Subject'] = 'Missing data in SimulationAnnex'
        msg['From'] = 'The Webserver <no-reply@black-holes.org>'
        msg['To'] = email
        # msg['To'] = '{0} <{1}>'.format(name, email)
        s = smtplib.SMTP('localhost')
        s.sendmail(msg['From'], [msg['To']], msg.as_string())
        # s.sendmail('The Webserver <cron@black-holes.org>', ['{0} <{1}>'.format(name, email)], msg.as_string())
        s.quit()

        # print(committer)
        # print('{0} <{1}>'.format(name, email))
        # print(email_template.format(first_name=first_name, error=committer_and_errors[committer]))
        # print('='*90)

