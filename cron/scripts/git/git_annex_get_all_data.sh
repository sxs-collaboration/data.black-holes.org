#! /bin/bash -e

# NOTE: This script should be run from inside the desired git annex repo directory

# Make sure that any new files get created with equal permissions for group and user
umask 0002

# Update the repo
git pull --rebase --quiet origin master
git annex merge > /dev/null

# Look for broken symlinks, and run `git annex get` on each
find . -not -path './hooks' -not -path './Support' -xtype l -exec git annex get {} \;
