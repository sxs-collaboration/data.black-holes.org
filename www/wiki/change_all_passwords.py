#! /usr/bin/env python

print("""\
# users.auth.php
# <?php exit()?>
# Don't modify the lines above
#
# Userfile
#
# Format:
#
# user:MD5password:Real Name:email:groups,comma,separated
#""")

import sys
import string
import random
from subprocess import check_output

# The dokuwiki passpolicy plugin considers special characters to be members of this list:
special_characters = r"""!"$%&/()=?{[]}\*+~'#,;.:-_<>|@"""
# But for our purposes, some of those won't work very well, so we cut it down to
special_characters = r"""!%()={[]}*+~#,;.:-_<>|@"""

password_characters = string.ascii_letters + string.digits + special_characters
password_length = 16
choice_function = random.SystemRandom().choice

with open('passwds', 'w') as passwds:
    for line in sys.stdin:
        if '#' in line:
            continue
        try:
            user, password, full_name, email, groups = line.strip().split(':')
        except:
            continue
        if user == 'fairchild':
            new_password = 'Fairchild_login@SXS'
        else:
            new_password = ''.join(choice_function(password_characters) for _ in range(password_length))
        passwds.write(':'.join([user, new_password, full_name, email, groups]) + '\n')
        new_user_and_hash = check_output('htpasswd -nbm {0} "{1}"'.format(user, new_password), shell=True).decode().strip()
        print(':'.join([new_user_and_hash, full_name, email, groups]))
