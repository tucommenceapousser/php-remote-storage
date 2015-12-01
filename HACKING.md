# Hacking
This document describes how to setup a development environment on your 
system and running tests.

# Requirements
You need to have at least PHP 5.4 with built-in web server support. The 
latest version is recommended.

## Fedora

    $ sudo dnf -y install php-cli php-pdo composer git

## Ubuntu

    $ sudo apt-get install php5-cli php5-curl php5-sqlite git

On Ubuntu you need to manually install [Composer](https://getcomposer.org). 

# Installation
Choose a working directory, clone the repository and run composer:

    $ git clone https://github.com/fkooman/php-remote-storage.git
    $ cd php-remote-storage
    $ composer install

Use the *dev* configuration file:

    $ cp config/server.dev.yaml.example config/server.yaml

You can start the PHP web server:

    $ php -S localhost:8080 -t web/ contrib/rs-router.php

You can now point your browser to 
[http://localhost:8080/](http://localhost:8080/) and you should see the welcome 
page. You can sign in with the user `foo` and password `bar` to the account 
page.

If you want to test with some applications, make sure they are not using
HTTPS as that will prevent most browsers from connecting to your service due
to blocking [Mixed Content](https://developer.mozilla.org/en-US/docs/Security/MixedContent).

## Development Mode
Development mode makes it possible to use the built in PHP web server. It 
changes the following things:

* non-secure HTTP cookies are allowed;
* `X-SendFile` will not be used;

Development can be enabled in the configuration file `config/server.yaml`:
    
    serverMode: development

Make sure you do not enable this in production or test environments!

# Testing
You can run the included unit tests with PHPUnit:

    $ vendor/bin/phpunit

# API test suite
Some extra dependencies are needed to run the API test suite:

## Fedora

    $ sudo dnf -y install rubygem-bundler ruby-devel gcc-c++ redhat-rpm-config

## Ubuntu
It seems on Ubuntu 14.04 the included Ruby is too old to deal with multi byte
characters.

    $ sudo apt-get install ruby-dev bundler

## The Test Suite
Now install the test suite:

    $ git clone https://github.com/remotestorage/api-test-suite.git
    $ cd api-test-suite
    $ bundle install

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

# Contributing
You can send a pull request, ideally after first discussing a new feature or
fix. Please make sure there is an accompanying unit test for your feature or 
fix. I know the current test coverage is not perfect, but trying to improve :)
