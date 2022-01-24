# Introduction

This document describes how to run the latest Git code on your system for 
remoteStorage server development.

In addition, instructions are provided to install a simple remoteStorage 
compatible application to test and play with the server.

We will be using PHP's built in web server to reduce the amount of 
configuration as much as possible.

This document will also describe how to run the integrated unit tests and how
to set up integration testing.

# Dependencies

## Fedora

```bash
$ sudo dnf -y install php-cli php-pdo composer git php-intl php-mbstring 
```

## Ubuntu

```bash
$ sudo apt install composer git php-xml php-intl php-sqlite3
```

# Installation

Choose a working directory, clone the repository and run composer:

```bash
$ git clone https://github.com/fkooman/php-remote-storage.git
$ cd php-remote-storage
$ composer update
$ mkdir data
$ cp config/server.dev.yaml.example config/server.yaml
```

And now start the server:

```bash
$ php -S localhost:8080 -t web/ contrib/rs-router.php
```

Use your browser to go to [http://localhost:8080/](http://localhost:8080/) to
get started! The default user is `foo` with password `bar`.

You can easily add more users if you want:

```bash
$ php bin/add-user.php me p4ssw0rd
```

## remoteStorage Application

You can install a simple remoteStorage application under the `web/` directory
as well:

```bash
$ cd web
$ curl -L -O https://github.com/remotestorage/myfavoritedrinks/archive/master.tar.gz
$ tar -xzf master.tar.gz
```

Now visit [http://localhost:8080/myfavoritedrinks-master/](http://localhost:8080/myfavoritedrinks-master/)
with your browser and connect to your storage using the user address 
`foo@localhost:8080`.

You should be able to store some drinks on your server, cheers! :)

# Unit Testing

You can run the included unit tests with PHPUnit:

```bash
$ vendor/bin/phpunit
```

# Integration Testing

Some extra dependencies are needed to run the API test suite:

## Fedora

```bash
$ sudo dnf -y install rubygem-bundler ruby-devel gcc-c++ redhat-rpm-config
```

## Ubuntu

It seems on Ubuntu 14.04 the included Ruby is too old to deal with multi byte
characters.

```bash
$ sudo apt-get install ruby-dev bundler
```

## The Test Suite

Now install the test suite:

```bash
$ git clone https://github.com/remotestorage/api-test-suite.git
$ cd api-test-suite
$ bundle install
```

You need to modify the configuration a bit to work with php-remote-storage:

```
$ cp config.yml.example config.yml
```

Edit the `config.yml` file, e.g.:

```
storage_base_url: http://localhost:8080/demo
storage_base_url_other: http://localhost:8080/bar
category: api-test
token: token
read_only_token: read_only_token
root_token: root_token
```

Now, the Bearer token validation of php-remote-storage needs to be modified,
this will be configurable soon instead of a hack in the source. Modify
`/var/www/php-remote-storage/web/index.php` from:

```php
$apiAuth = new BearerAuthentication(
    new DbTokenValidator($db),
    array('realm' => 'remoteStorage')
);
```

To:

```php
$apiAuth = new BearerAuthentication(
    new fkooman\RemoteStorage\ApiTestTokenValidator(),
    array('realm' => 'remoteStorage')
);
```

Now you can run the test suite and all should be fine:

```bash
$ rake test
```

# Contributing

You can send a pull request, ideally after first discussing a new feature or
fix. Please make sure there is an accompanying unit test for your feature or 
fix. I know the current test coverage is not perfect, but trying to improve :)
