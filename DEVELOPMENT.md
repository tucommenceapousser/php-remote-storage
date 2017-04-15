# Introduction

This document describes how to run official releases on your system for 
application development.

In addition, instructions are provided to install a simple remoteStorage 
compatible application to test and play with the server.

We will be using PHP's built in web server to reduce the amount of 
configuration as much as possible.

# Dependencies

## Fedora

    $ sudo dnf -y install php-cli php-pdo

## Ubuntu/Debian

    $ sudo apt-get install php5-cli php5-curl php5-sqlite php5-mbstring

## Arch Linux

    $ sudo pacman -S php php-sqlite

Make sure to uncomment `openssl.so` and `pdo_sqlite.so` in `/etc/php/php.ini`.

## Mac OS X

All dependencies should already be installed on current versions of OS X.

# Downloading
The releases can be downloaded from a remoteStorage server running this 
software.

Stable releases will also be hosted on GitHub, for now the test releases are 
only available from my remoteStorage server instance:

* from [remoteStorage](https://storage.tuxed.net/fkooman/public/upload/php-remote-storage/releases.html)
* from [GitHub](https://github.com/fkooman/php-remote-storage/releases)

# Installing
After downloading and extracting the software, it is easy to run it in 
development mode:

    $ tar -xJf php-remote-storage-VERSION.tar.xz
    $ cd php-remote-storage-VERSION
    $ cp config/server.dev.yaml.example config/server.yaml

You can easily add more users if you want:

    $ php bin/add-user.php me p4ssw0rd

# Running
Start the server:

    $ php -S localhost:8080 -t web/ contrib/rs-router.php

Use your browser to go to [http://localhost:8080/](http://localhost:8080/) to
get started! The default user is `foo` with password `bar`. You can also use
the user(s) you added in the previous section.

# remoteStorage Application
You can install a simple remoteStorage application under the `web/` directory
as well:

    $ cd web
    $ curl -L -O https://github.com/remotestorage/myfavoritedrinks/archive/master.tar.gz
    $ tar -xzf master.tar.gz

Now visit [http://localhost:8080/myfavoritedrinks-master/](http://localhost:8080/myfavoritedrinks-master/)
with your browser and connect to your storage using the user address 
`foo@localhost:8080`.

You should be able to store some drinks on your server, cheers! :)
