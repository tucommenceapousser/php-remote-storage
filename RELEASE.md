# Introduction
This document describes how to run offical releases on your system, for 
testing and simple deploys. See the 
[deployment repository](https://github.com/fkooman/php-remote-storage-deployment/) 
for information on how to deploy for production environments. 

In addition a simple remoteStorage compatible application can be installed 
that can be used to test and play with the server.

See [HACKING.md](HACKING.md) for setting up a development environment.

# Dependencies
## Fedora

    $ sudo dnf -y install php-cli php-pdo

## Ubuntu

    $ sudo apt-get install php5-cli php5-curl php5-sqlite

## Arch Linux

    $ sudo pacman -S php php-sqlite

Make sure to uncomment `openssl.so` and `pdo_sqlite.so` in `/etc/php/php.ini`.

# Downloading
The releases can be downloaded from GitHub, or from a remoteStorage server 
running this software.

* from [GitHub](https://github.com/fkooman/php-remote-storage/releases)
* from [remoteStorage](https://storage.tuxed.net/fkooman/public/upload/php-remote-storage/releases.html)

# Running
After downloading and extracting the software, it is easy to run it in 
development mode:

    $ tar -xJf php-remote-storage-VERSION.tar.xz
    $ cd php-remote-storage-VERSION
    $ cp config/server.dev.yaml.example config/server.yaml
    $ mkdir -p data/storage
    $ php bin/php-remote-storage-init

And now start it:

    $ php -S localhost:8080 -t web/ contrib/rs-router.php

Use your browser to go to [http://localhost:8080/](http://localhost:8080/) to
get started! The default user is `foo` with password `bar`.

You can simply add users like this:

    $ php bin/php-remote-storage-add-user me p4ssw0rd

# remoteStorage Application
You can install a sample remoteStorage application under the `web` directory
as well to the server:

    $ cd web
    $ curl -L -O https://github.com/remotestorage/myfavoritedrinks/archive/master.tar.gz
    $ tar -xzf master.tar.gz

Now visit [http://localhost:8080/myfavoritedrinks-master/](http://localhost:8080/myfavoritedrinks-master/)
with your browser and connect to your storage using the user address 
`foo@localhost:8080`.

You should be able to store some drinks on your server! :)

# Using a Web Server
To run this software on a web server is a bit more complex, you need to have
the ability to modify your web server's configuration file, added complexity 
is the WebFinger support that may be needed.

TBD.
