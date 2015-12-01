# Introduction

This document describes how to run official releases for simple deployments.

In addition, instructions are provided to install a simple remoteStorage 
compatible application to test and play with the server.

We will be using the Apache web server with `mod_php`. This is the most common
deployment scenario, and most people will have some familiarity with this.

This document will assume you use the name `storage.local` for your server. If
you have your own domain name you can use that instead. These instructions 
configure the storage server on its own domain, as that is required for making
WebFinger work without hackery.

If you use `storage.local`, you can configure it in `/etc/hosts`:

    1.2.3.4     storage.local

If you choose your own domain name, replace all occurrences below with that 
domain name and do not forget to edit the web server configuration file 
accordingly!

Of course, dealing with TLS, one MUST verify the TLS configuration. Typically, 
I use both these services:

* [SSL Decoder](https://ssldecoder.org/)
* [SSL Labs](https://www.ssllabs.com/ssltest/)

# Dependencies

## Fedora

    $ sudo dnf -y install httpd php php-pdo php-mbstring mod_ssl mod_xsendfile /usr/sbin/semanage

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

## Common

After downloading, extract the software in `/var/www`:

    $ cd /var/www
    $ sudo tar -xJf /path/to/php-remote-storage-VERSION.tar.xz
    $ sudo mv php-remote-storage-VERSION php-remote-storage
    $ cd php-remote-storage
    $ sudo cp config/server.yaml.example config/server.yaml

Now add a user, by default no users are set up in the production template:

    $ sudo bin/add-user me p4ssw0rd

## Fedora

The instructions here are specific for Fedora.

Prepare the `data` directory for storing files, the database and template 
cache:

    $ sudo mkdir data
    $ sudo chown apache.apache data
    $ sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/php-remote-storage/data(/.*)?"
    $ sudo restorecon -R data

Generate the SSL certificate:

    $ sudo openssl genrsa -out /etc/pki/tls/private/storage.local.key 2048
    $ sudo chmod 600 /etc/pki/tls/private/storage.local.key
    $ sudo openssl req -subj "/CN=storage.local" -sha256 -new -x509 \
        -key /etc/pki/tls/private/storage.local.key \
        -out /etc/pki/tls/certs/storage.local.crt

Install the Apache configuration file:

    $ sudo cp contrib/storage.local.conf.fedora /etc/httpd/conf.d/storage.local.conf

Enable the web server on boot and start it:

    $ sudo systemctl enable httpd
    $ sudo systemctl start httpd

If you want to have your certificate signed by a CA you can also generate a 
CSR:

    $ sudo openssl req -subj "/CN=storage.local" -sha256 -new \
        -key /etc/pki/tls/private/storage.local.key \
        -out storage.local.csr

Once you obtain the resulting certificate, overwrite the file 
`/etc/pki/tls/certs/storage.local.crt` with the new certificate, configure the
chain and restart the web server.

## Ubuntu/Debian

The instructions here are specific for Ubuntu/Debian.

Prepare the `data` directory for storing files, the database and template 
cache:

    $ sudo mkdir data
    $ sudo chown www-data.www-data data

Generate the SSL certificate:

    $ sudo openssl genrsa -out /etc/ssl/private/storage.local.key 2048
    $ sudo chmod 600 /etc/ssl/private/storage.local.key
    $ sudo openssl req -subj "/CN=storage.local" -sha256 -new -x509 \
        -key /etc/ssl/private/storage.local.key \
        -out /etc/ssl/certs/storage.local.crt

Install the Apache configuration file:

    $ sudo cp contrib/storage.local.conf.ubuntu /etc/apache2/sites-available/storage.local.conf

Enable some web server modules and enable the site:

    $ sudo a2enmod rewrite
    $ sudo a2enmod headers
    $ sudo a2enmod ssl
    $ sudo a2ensite default-ssl
    $ sudo a2ensite storage.local
    $ sudo service apache2 restart

If you want to have your certificate signed by a CA you can also generate a 
CSR:

    $ sudo openssl req -subj "/CN=storage.local" -sha256 -new \
        -key /etc/ssl/private/storage.local.key \
        -out storage.local.csr

Once you obtain the resulting certificate, overwrite the file 
`/etc/ssl/certs/storage.local.crt` with the new certificate, configure the
chain and restart the web server.
