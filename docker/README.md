# Introduction
These are all the files to get a Docker instance running with 
`php-remote-storage`. This includes `php-oauth-as` and `php-simple-auth` as 
well.

To build the Docker image:

    docker build --rm -t fkooman/php-remote-storage .

To run the container:

    docker run -d -p 443:443 fkooman/php-remote-storage

That should be all. You can replace `fkooman` with your own name of course.

To run, you first have to accept the self-signed certifcate at 
`https://localhost`. After this you can for example try 
`https://webmarks.5apps.com/` and use `admin@localhost` or `fkooman@localhost`
as usernames with `adm1n` and `foobar` as passwords.

# SELinux
It seems there is an issue on Ubuntu 14.04 with SELinux. Running the container
will result in an Apache error.

The fix may be to enable SELinux for docker:

    To run the daemon with SELinux support, use docker -d --selinux-enabled

[Source](http://blog.docker.com/2014/05/docker-0-11-release-candidate-for-1-0/)
