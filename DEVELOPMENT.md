# Development
This document describes how to setup a development environment on your 
system.

# Requirements
You need to have at least PHP 5.4 with built-in web server support. The 
latest version is recommended.

The following PHP modules need to be installed and activated:

* json
* spl
* pdo

## Fedora

    $ sudo dnf -y install php-cli php-pdo composer git php-phpunit-PHPUnit

## Ubuntu

    $ sudo apt-get install php5-cli php5-curl php5-sqlite git phpunit

On Ubuntu you need to manually install [Composer](https://getcomposer.org). 

# Installation
Choose a working directory, clone the repository and run composer:

    $ git clone https://github.com/fkooman/php-remote-storage.git
    $ cd php-remote-storage
    $ composer install

Copy the configuration file:

    $ cp config/server.yaml.example config/server.yaml

Modify it to enable development mode and point to the correct locations for 
storage and the database. Here is a minimal configuration that should work:

    storageDir: /home/fkooman/Projects/php-remote-storage/data/storage
    serverMode: development
    Users:
        # foo:bar
        foo: $2y$10$sWzE0MjAP13srnNI/Pg8SuBM6LVmq8/hnznJwkQRF00Obe321PqGq
    Db:
        dsn: 'sqlite:/home/fkooman/Projects/php-remote-storage/data/rs.sqlite'

Create the directory and initialize the database:
    
    $ mkdir -p data/storage
    $ php bin/php-remote-storage-init

Now you can start the service from the CLI:

    $ php -S localhost:8080 -t web/ contrib/rs-router.php

You can now point your browser to http://localhost:8080/ and you should see
the welcome page.

## Development Mode
Development mode disables some features to make it work with the PHP 
web server:

* non-secure HTTP cookies are allowed;
* `X-SendFile` will not be used;

Make sure you do not enable this in production or test environments!

# Testing
You can run the included unit tests with PHPUnit:

    $ phpunit

# API test suite
Some extra dependencies are needed to run the API test suite:

    $ sudo dnf -y install rubygem-bundler ruby-devel gcc-c++ redhat-rpm-config

Now install the test suite:

    $ git clone https://github.com/remotestorage/api-test-suite.git
    $ cd api-test-suite
    $ bundler install

You need to modify the configuration a bit to work with php-remote-storage:

    $ cp config.yml.example config.yml

Edit the `config.yml` file, e.g.:

    storage_base_url: http://localhost:8080/demo
    storage_base_url_other: http://localhost:8080/bar
    category: api-test
    token: token
    read_only_token: read_only_token
    root_token: root_token

Now, the Bearer token validation of php-remote-storage needs to be modified,
this will be configurable soon instead of a hack in the source. Modify
`/var/www/php-remote-storage/web/index.php` from:

    $apiAuth = new BearerAuthentication(
        new DbTokenValidator($db),
        array('realm' => 'remoteStorage')
    );

To:

    $apiAuth = new BearerAuthentication(
        new fkooman\RemoteStorage\ApiTestTokenValidator(),
        array('realm' => 'remoteStorage')
    );

Now you can run the test suite and all should be fine:

    $ rake test

