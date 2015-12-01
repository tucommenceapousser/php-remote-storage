# Introduction

This document describes how to run official releases on your system, for 
testing and simple deployments.

In addition, instructions are provided to install a simple remoteStorage 
compatible application to test and play with the server.

See [HACKING.md](HACKING.md) for setting up a development environment.

This document will describe two ways to run the software: 

1. using PHP's built in web server. Use this if you are developing 
   remoteStorage compatible applications; 
2. using Apache and `mod_php`. Use this for simple deployments on your personal
   server.

If you want to deploy for production environments take a look at the 
[deployment](https://github.com/fkooman/php-remote-storage-deployment) repository.

# Built in Web Server
## Dependencies

### Fedora

    $ sudo dnf -y install php-cli php-pdo

### Ubuntu/Debian

    $ sudo apt-get install php5-cli php5-curl php5-sqlite

### Arch Linux

    $ sudo pacman -S php php-sqlite

Make sure to uncomment `openssl.so` and `pdo_sqlite.so` in `/etc/php/php.ini`.

### Mac OS X

All dependencies should already be installed on current versions of OS X.

## Downloading
The releases can be downloaded from a remoteStorage server running this 
software.

Stable releases will also be hosted on GitHub, for now the test releases are 
only available from my remoteStorage server instance:

* from [remoteStorage](https://storage.tuxed.net/fkooman/public/upload/php-remote-storage/releases.html)
* from GitHub (after first stable release)

## Running
After downloading and extracting the software, it is easy to run it in 
development mode:

    $ tar -xJf php-remote-storage-VERSION.tar.xz
    $ cd php-remote-storage-VERSION
    $ cp config/server.dev.yaml.example config/server.yaml

And now start the server:

    $ php -S localhost:8080 -t web/ contrib/rs-router.php

Use your browser to go to [http://localhost:8080/](http://localhost:8080/) to
get started! The default user is `foo` with password `bar`.

You can simply add more users:

    $ bin/add-user me p4ssw0rd

## remoteStorage Application
You can install a simple remoteStorage application under the `web/` directory
as well:

    $ cd web
    $ curl -L -O https://github.com/remotestorage/myfavoritedrinks/archive/master.tar.gz
    $ tar -xzf master.tar.gz

Now visit [http://localhost:8080/myfavoritedrinks-master/](http://localhost:8080/myfavoritedrinks-master/)
with your browser and connect to your storage using the user address 
`foo@localhost:8080`.

You should be able to store some drinks on your server, cheers! :)

# Apache and mod_php
To run this software on a web server is a bit more complicated, you need to 
have the ability to modify your web server's configuration files. Added 
complexity is the WebFinger support which needs to run under the domain's root. 
Therefore we create a new `VirtualHost` in Apache. To keep things simple we 
will only create a `VirtualHost` without TLS. The instructions below will 
assume you unpacked the release in `/var/www/php-remote-storage`.

You also may need to add an entry to your `/etc/hosts` if you do not have a DNS
name pointing to your `VirtualHost` host name, e.g.:

    127.1.2.3       storage.local

## Fedora

    $ sudo dnf -y install httpd php php-pdo mod_ssl mod_xsendfile /usr/sbin/semanage
    $ mkdir -p data/
    $ sudo chown -R apache.apache data/

You also need to fix the SELinux labels for the web server to be able to 
write to the storage folder and database:

    $ sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/php-remote-storage/data(/.*)?"
    $ sudo restorecon -R /var/www/php-remote-storage/data"

You can copy the Apache configuration from `contrib/storage.local.conf` to 
`/etc/httpd/conf.d/storage.local.conf`.

Enable and start Apache:

    $ sudo systemctl enable httpd
    $ sudo systemctl start httpd

You should be able to visit [http://storage.local/](http://storage.local/) now,
you can follow the "remoteStorage Application" instructions above to install 
an application. Use the identity `foo@storage.local` to connect to your 
storage server.

## Ubuntu/Debian

    $ sudo apt-get install apache2 php5 php5-curl libapache2-mod-xsendfile php5-sqlite
    $ mkdir -p data/
    $ sudo chown -R www-data.www-data data/

You can copy the Apache configuration from `contrib/storage.local.conf` to 
`/etc/apache2/sites-available/storage.local`.

Enable the site and restart Apache:

    $ sudo a2enmod rewrite
    $ sudo a2enmod ssl
    $ sudo a2ensite storage.local
    $ sudo service apache2 restart

You should be able to visit [http://storage.local/](http://storage.local/) now!
