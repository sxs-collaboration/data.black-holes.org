This repo contains the basic infrastructure for the `black-holes.org` site.  There are still several
components that are too big for or simply not appropriate for git hosting.  Those other components
include:

  * Joomla and its database (which serves most of the public site)
  * Dokuwiki
  * SimulationAnnex.git (for /data/waveforms)
  * SurrogateModeling.git (for /data/surrogates)

While those components are backed up and/or updated using scripts in this repo, they are not parts
of this repo directly.

We (mostly Nils) have been making every effort to move as much as possible off of this server and
into standard solutions like github.  In particular, the spec-bugs (trac) and doxygen components
have moved to github issues and sxs-test, respectively.


# Updating this repo and making changes live

Please make Pull Requests for any changes you make so that Mike can review them, unless the changes
are minor (e.g., wording changes on static pages, or additions to the `references.bib` file).  Before
starting any less minor changes, at least read the rest of this page so that you understand how this
repo works, and feel free to ping Mike to let him know what you're going to try so that he can give
you feedback about how well that will work.

After changes have been merged to the master branch, the repo needs to be updated on the web server
(see below), and depending on what has changed the docker containers may need to be restarted (see
below).  Please do not try to do this yourself unless Mike has been hit by a bus; just ask him.


# Brief overview

The website works through docker containers.  To understand the containers themselves, look at
`www/docker-compose.yml`, which specifies details of each container.  In some cases, the
specification contains the field `build`, which points to a directory containing a `Dockerfile`, and
possibly a few other necessary files.  In those cases, the `Dockerfile` describes how docker should
build that container.  All of these files are simple enough to be essentially self-explanatory, or
easily understood from the comments.

On the other hand, if you are only interested in how web requests are handled, you probably do not
need to understand details about the containers.  All web requests go to the `nginx` container,
which mostly farms requests out to other containers.  So to understand how web requests are handled,
a good first place to look is in the `www/nginx/nginx.conf` file.  That file is extensively
commented, including an overview at the top of the file.

The other main task is updating various components, which is done by the `cron` container.


# File locations

In what follows, "the server" is the CentOS machine at `black-holes.tapir.caltech.edu` — which will
be abbreviated to `black-holes.tapir`, as opposed to the website's domain `black-holes.org`.

Most of the files relevant to the website are located on `black-holes.tapir` in `/web/servers`.
Certain very large directories (such as the SimulationAnnex) are located in `/sxs-annex` and
`/sxs-annex8`.

There are also three "secrets" files that are assumed to exist in the `../secrets` directory
(relative to the directory this file is in), which are used by the `oauth2` container.  For details,
see the [`oauth2` container README](https://github.com/sxs-collaboration/black-holes.org/tree/master/www/oauth2#readme).


# Cron jobs

There are several external data sources that are updated routinely (and backups performed) using
cron jobs.  Many of these sources are also served directly through the web interface.

  * The SSL certificate provided by letsencrypt.org
  * The waveform catalog's index
  * SimulationAnnex and SurrogateModeling git repos
  * Permissions are corrected frequently
  * Incremental backups are taken nightly (see below)


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


## Network connections

### Between containers

The docker containers can generally communicate with each other.  Essentially, they are all on their
own network, and each container can be addressed by its name.  For example, the `nginx` container is
simply `nginx` to every other container.  For example, if the `wiki` container wants to send it a
web request, it can just curl `https://nginx/`.  These names are automatically found through the
docker network's DNS resolver on 127.0.0.11.  (If the name is not present on the "local" network,
the resolver just looks to Google's DNS resolver.)

### From outside internet to containers

Only one container is visible to the rest of the internet: `nginx` has ports 80 (http) and 443
(https) open and linked to the same ports on `black-holes.tapir`.  Thus, all requests for
`black-holes.org:80` go to the docker container `nginx:80`.  Similarly, all requests for
`black-holes.org:443` go to the docker container `nginx:443`.  No other ports are accessible from
the outside.  In particular, requests to `black-holes.org:22` (ssh) get handled by the server
itself, and don't have direct access to any containers.

Therefore, understanding where requests to our web server go means understanding what `nginx` does
with those requests.  See the explanation in the comments of `www/nginx/nginx.conf`.

### From containers to the internet

If a container requests access to a host that is not found on the "local" docker network, the DNS
resolver just looks up the address on Google's nameservers (8.8.8.8 and 8.8.4.4).  Thus, all
containers can access the internet.

### From a login on the server to containers

[Not really a networking thing per se, but related.]  If you have SSHed onto black-holes.tapir, it
is possible to get a shell on most of the running containers.  This can be useful for certain
debugging tasks, or testing out changes that might be made to the containers themselves.  First,
find the running container by issuing `docker ps`, and note the name of the container.  Then, issue
a command like `docker exec -it <container_name> bash`, which will drop you into a `bash` shell
running on that container.  Some containers will not have `bash` installed, and may not have any
type of interactive program available.

Note that any changes you make to the container while it is running will not be persistent; they
will not be there the next time the container starts up, even if you do something like `docker
commit`.  To make such changes, you will have to modify the docker-compose entry, Dockerfile,
entrypoint script, or command used to start the container.  The sole exception to this rule is when
the changes you have made affect any of the files in volumes that also exist on the host.
