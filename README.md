| NOTE: If the server is unresponsive, the most likely solution is to just restart the docker containers.  To do this, ssh into black-holes, `cd /web/servers/www/`, and run `../stop && ../start`.  That resolves 95% of the problems I've had with the server.  Most of the other problems have been because `/var/log` has filled up; just delete old files from in there.  —Mike |
| --- |

This repo contains the basic infrastructure for the `data.black-holes.org` site.

After changes have been merged to the main branch, the repo needs to be updated on the web server
(see below), and depending on what has changed the docker containers may need to be restarted (see
below).  Please do not try to do this yourself unless Mike has been hit by a bus; just ask him.


# Brief overview

The website works through docker containers.  To understand the containers themselves, look at
`docker-compose.yml`, which specifies details of the container.

On the other hand, if you are only interested in how web requests are handled, you probably do not
need to understand details about the containers.  All web requests go to the
`caddy_data_black_holes` container.  So to understand how web requests are handled, a good first
place to look is in the `Caddyfile` file.


# File locations

In what follows, "the server" is the CentOS machine at `black-holes.tapir.caltech.edu` — which will
be abbreviated to `black-holes.tapir`, as opposed to the website's domain `black-holes.org`.

The files relevant to the website are located on `black-holes.tapir` in `/web/servers`.

## Backups

The entire `/web/servers` directory is backed up every night to `/sxs-annex/web_server_backup`.
Backups are performed using `rsync`'s `--link-dest` option.  This means that the first backup is a
complete copy (which will be quite large), but subsequent backups consist primarily of hard links to
a previous backup — the one at which a given file changed.  This reduces the size of backups
drastically, while still allowing the backup directory to look and act like a full copy.

To find files that are *not* hard links in the backup directory, use a command like

    find /sxs-annex/web_server_backup/current/ -type f -links 1 -exec du -ch {} +

(Or to just see the total size, add `| grep total$` to that command.)  The results will generally be
caches, a few database tables, and anything in the wiki that has changed.  Typical backup sizes are
in the neighborhood of 220M, dominated almost entirely by joomla database files.  (Even though
joomla can no longer be changed, it still keeps track of user sessions for example, which causes a
few large files to change constantly.)


# Docker

As much as possible, everything has been moved into docker.  The server itself is basically only
responsible for running docker, the firewall, and ssh, and a few other standard self-maintenance
tasks.  So to understand how the website or associated pieces work, it helps to understand the
docker setup.

There are just a few key ideas needed to understand how docker is working, which can mostly be
gleaned by looking at the relevant `docker-compose.yml` file, in either `www` or `cron`.  Under the
`services` headings, these list a number of containers named `nginx`, `joomladb`, `cron`, etc.  In
each of these, there are just a few main things to note, several of which may be defaults:

  1. The `image` or `build`
     * If `image` is given, the container just copies some existing image from dockerhub.  For
       example, `nginx` has an official image, so docker just copies that and runs it.
     * If `build` is given, it points to a directory containing a `Dockerfile` (and possibly some
       related files).  This gives the image on which the container is based, as well as a number of
       commands to run to set up the container before it is run, and then
  2. The `volumes` (optional) — files or directories added to the container.
     * If there is just one path, this volume is added to the container, but does not communicate
       with anything off the container, and will cease to exist once the container is stopped.
     * If there are two paths separated by a colon, the first is the path on the host computer,
       while the second is where it appears in the container.  Changes made to the volume on the
       host will appear on the container, and vice versa.  These changes will persist after the
       container is stopped.
     * With two paths, it is also possible to add `:ro` at the end, making the volume read-only on
       the container.
  3. The `ports` (optional) — ports opened on the container, and possibly connected to the host's
     ports.
     * If there is just one port, it is opened on the container, but only accessible from other
       containers on the container's docker network (basically, anything else from the same
       `docker-compose.yml` file).
     * If there are two ports, the first is exposed on the host, and connected to the second which
       is a port on the container.  For example, `8080:80` would specify that port 8080 on the host
       would receive connections, and pass them directly to port 80 on the container.  (Note that
       the host's firewall may still block 8080.)
  4. The `entrypoint` or `command` (optional) — basically a way to identify the executable to run
     when the container is started.
     * The details of these two entries are hard to explain.  If it matters, google it.
     * These may not be given in either `docker-compose.yml` or `Dockerfile`; if so, they are
       inherited from the original image on which the container was based.


# DNS configuration

Our domain is registered and DNS provided by aplus.net.  The DNS configuration looks like this:
```
Type	Name	Value	TTL	Add DNS Record
A		131.215.193.115		
A	www	131.215.193.115		
A	sxs-test	131.215.193.151		
CNAME	*	black-holes.org.		
CNAME	data	black-holes.org.		
CNAME	sxs-annex.cornell	132.236.6.125.		
CNAME	sxs-annex8.cornell	132.236.6.127.		
MX	1	ASPMX.L.GOOGLE.COM.		
MX	5	ALT1.ASPMX.L.GOOGLE.COM.		
MX	5	ALT2.ASPMX.L.GOOGLE.COM.		
MX	10	ALT3.ASPMX.L.GOOGLE.COM.		
MX	10	ALT4.ASPMX.L.GOOGLE.COM.		
TXT	_github-challenge-sxs-collaboration.www.black-holes.org	"69eace9891"		
```
