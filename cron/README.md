This container runs cron jobs from any file in the `./cron.d` directory.  This is mounted within the
container as `/etc/cron.d`, which is searched by cron every minute.  Changes to these files can be
made on-the-fly, so that nothing needs to be done to the container for changes to take effect.

**NOTE**: Crontab files in the `cron.d` directory have the *system* crontab format, which means they
have a required additional username field immediately after the time and date fields.

**NOTE**: The `cron.d` directory and its contents must be owned by root, and not readable or
writable by `group` or `other`.

As a reminder, the time and date specification is given by this:

> Times at which the commands should be run are specified by the first six fields.  An asterisk
> means that the command should run for any value of that field.  Fields can be lists of times,
> separated by commas.  If the field ends in `/n` for some integer `n`, the command is executed
> every `n` of the times indicated by the first part of that field.  The units are:  
>  
>   ┌───────────── minute (0 - 59)  
>   │ ┌───────────── hour (0 - 23)  
>   │ │ ┌───────────── day of month (1 - 31)  
>   │ │ │ ┌───────────── month (1 - 12)  
>   │ │ │ │ ┌───────────── day of week (0 - 6) (Sunday to Saturday;  
>   │ │ │ │ │                                   7 is also Sunday on some systems)  
>   │ │ │ │ │  
>   │ │ │ │ │  
>   \* * * * * username command to execute  

So, to run `echo "hello"` as user `root` every 3 minutes, the input would look like this:

    */3 * * * * root echo "hello"

Capture any output you want logged and not considered an error with either `>> /var/log/cron.log`
(to capture only stdout) or `>> /var/log/cron.log 2>&1` (to capture both stdout and stderr).  Any
other output (even to stdout) will cause cron to email the output to the `MAILTO` address.

From `man cron` on debian:

> cron also reads /etc/crontab, which is in a slightly different format (see crontab(5)).  In
> Debian, the content of /etc/crontab is predefined to run programs under /etc/cron.hourly,
> /etc/cron.daily, /etc/cron.weekly and /etc/cron.monthly. This configuration is specific to Debian,
> see the note under DEBIAN SPECIFIC below.
> 
> Additionally, in Debian, cron reads the files in the /etc/cron.d directory.  cron treats the files
> in /etc/cron.d as in the same way as the /etc/crontab file (they follow the special format of that
> file, i.e. they include the user field). However, they are independent of /etc/crontab: they do
> not, for example, inherit environment variable settings from it. This change is specific to Debian
> see the note under DEBIAN SPECIFIC below.
> 
> Like /etc/crontab, the files in the /etc/cron.d directory are monitored for changes. In general,
> the system administrator should not use /etc/cron.d/, but use the standard system crontab
> /etc/crontab.
> 
> /etc/crontab and the files in /etc/cron.d must be owned by root, and must not be group- or
> other-writable. In contrast to the spool area, the files under /etc/cron.d or the files under
> /etc/cron.hourly, /etc/cron.daily, /etc/cron.weekly and /etc/cron.monthly may also be symlinks,
> provided that both the symlink and the file it points to are owned by root.  The files under
> /etc/cron.d do not need to be executable, while the files under /etc/cron.hourly, /etc/cron.daily,
> /etc/cron.weekly and /etc/cron.monthly do, as they are run by run-parts (see run-parts(8) for more
> information).
