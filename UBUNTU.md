# Introduction
This document describes how to setup php-remote-storage on Ubuntu. We tested 
with version 14.04.3 LTS.

**NOTE**: this is the development and/or test setup, not suitable for 
production!

# Dependencies
Install all updates, and the following dependencies:

    $ sudo apt-get update
    $ sudo apt-get dist-upgrade
    $ sudo apt-get install apache2 php5 git php5-curl \
        libapache2-mod-xsendfile php5-sqlite

Also set the system hostname:

    $ sudo hostnamectl set-hostname storage.example

# Installation

## Apache 
Some Apache modules need to be enabled:

    $ sudo a2enmod rewrite
    $ sudo a2enmod ssl

## remoteStorage

    $ cd /var/www
    $ sudo mkdir php-remote-storage
    $ sudo chown ubuntu.ubuntu php-remote-storage
    $ git clone https://github.com/fkooman/php-remote-storage.git

Now you need to get [Composer](https://getcomposer.org) and use that to 
install the PHP dependencies of the software.

    $ cd php-remote-storage
    $ curl -O https://getcomposer.org/composer.phar
    $ php composer.phar install

Copy the example configuration file:

    $ cp config/server.yaml.example config/server.yaml

Modify at least the following path references, you can remove the template
cache for development/testing:

    storageDir: /var/www/php-remote-storage/data/storage
    Db:
        dsn: 'sqlite:/var/www/php-remote-storage/data/rs.sqlite'

Now create the storage directory, and initialize the database:

    $ mkdir -p data/storage
    $ sudo chown -R www-data.www-data data
    $ sudo -u www-data php bin/php-remote-storage-init

Add a user:

    $ php bin/php-remote-storage-add-user foo bar

### Apache
Add configuration snippets for Apache. Put the following in 
`/etc/apache2/conf-available/php-remote-storage.conf`:

    Alias /php-remote-storage /var/www/php-remote-storage/web

    <Directory /var/www/php-remote-storage/web>
        AllowOverride None

        Require local
        #Require all granted

        XSendFile on
        XSendFilePath /var/www/php-remote-storage/data

        RewriteEngine On
        RewriteBase /php-remote-storage
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^(.*)$ index.php/$1 [QSA,L]

        SetEnvIfNoCase ^Authorization$ "(.+)" HTTP_AUTHORIZATION=$1
    </Directory>

Enable the configuration:

    $ sudo a2enconf php-remote-storage

## WebFinger

    $ cd /var/www
    $ sudo mkdir php-webfinger
    $ sudo chown ubuntu.ubuntu php-webfinger
    $ git clone https://github.com/fkooman/php-webfinger.git
    $ cd php-webfinger
    $ curl -O https://getcomposer.org/composer.phar
    $ php composer.phar install

Enable the default WebFinger configuration snippets:

    $ cp config/conf.d/php-remote-storage-03.conf.example config/conf.d/php-remote-storage-03.conf
    $ cp config/conf.d/php-remote-storage-05.conf.example config/conf.d/php-remote-storage-05.conf

### Apache
And the following in `/etc/apache2/conf-available/php-webfinger.conf`:

    Alias /.well-known/webfinger /var/www/php-webfinger/web/index.php

    <Directory /var/www/php-webfinger/web>
        AllowOverride None

        Require local
        #Require all granted
    </Directory>

Enable the configuration:

    $ sudo a2enconf php-webfinger
