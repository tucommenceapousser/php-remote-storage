# Introduction

This document describes how to run official releases for simple deployments.

In addition, instructions are provided to install a simple remoteStorage 
compatible application to test and play with the server.

We will be using the Apache web server with `mod_php`. This is the most common
deployment scenario, and most people will have some familiarity with this.

# Dependencies

## Fedora

    $ sudo dnf -y install httpd php php-pdo mod_ssl mod_xsendfile /usr/sbin/semanage

## Ubuntu/Debian

    $ sudo apt-get install apache2 php5 php5-curl libapache2-mod-xsendfile php5-sqlite

# Downloading

The releases can be downloaded from a remoteStorage server running this 
software.

Stable releases will also be hosted on GitHub, for now the test releases are 
only available from my remoteStorage server instance:

* from [remoteStorage](https://storage.tuxed.net/fkooman/public/upload/php-remote-storage/releases.html);
* from GitHub (after first stable release).

# Installing

After downloading, extract the software in `/var/www`. This works on both
Fedora and Ubuntu/Debian:

    $ cd /var/www
    $ sudo tar -xJf /path/to/php-remote-storage-VERSION.tar.xz
    $ sudo ln -s php-remote-storage-VERSION php-remote-storage
    $ cd php-remote-storage
    $ sudo cp config/server.yaml.example config/server.yaml
    $ sudo mkdir data

Now add a user, by default no users are set up:

    $ bin/add-user me p4ssw0rd

## Fedora

    $ sudo chown apache.apache data
    $ sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/php-remote-storage/data(/.*)?"
    $ sudo restorecon -R /var/www/php-remote-storage/data"

### SSL Certificate

TBD.

## Ubuntu/Debian

    $ sudo chown www-data.www-data data

### SSL Certificate

TBD.

# Apache 

## Fedora

    $ sudo cp contrib/storage.local.conf /etc/httpd/conf.d/storage.local.conf

## Ubuntu/Debian

    $ sudo cp contrib/storage.local.conf /etc/apache2/sites-available/storage.local
    $ sudo a2enmod rewrite
    $ sudo a2enmod ssl
    $ sudo a2ensite storage.local

# Running

## Fedora

    $ sudo systemctl enable httpd
    $ sudo systemctl start httpd

## Ubuntu/Debian

You should be all set on Ubuntu/Debian :)
