#!/bin/sh

set -e

# Define the locations to back up and to back up to
backed_up=/web/servers
backup_dir=/sxs-annex/web_server_backup

# Figure out the name of the next backup
next=$(date "+backup_%Y_%m_%d_%H%M")

# Do the next backup
if [ -d $backup_dir/current ]; then
    # If a link to the current backup exists, use it as the basis for this backup
    rsync -aq --link-dest=$backup_dir/current $backed_up/ $backup_dir/incomplete_$next
else
    # Otherwise, do a complete backup
    rsync -aq $backed_up/ $backup_dir/incomplete_$next
fi

# Remove `incomplete_` prefix and recreate link to the new current backup
mv $backup_dir/incomplete_$next $backup_dir/$next
rm -f $backup_dir/current
ln -sf $backup_dir/$next $backup_dir/current
