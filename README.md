[![Build Status](https://travis-ci.org/fkooman/php-remote-storage.png?branch=master)](https://travis-ci.org/fkooman/php-remote-storage)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/fkooman/php-remote-storage/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/fkooman/php-remote-storage/?branch=master)

# Introduction
This is a remoteStorage server implementation written in PHP. It aims at 
implementing `draft-dejong-remotestorage-03.txt` and higher.

# Deployment
See the [deployment](https://github.com/fkooman/php-remote-storage-deployment) 
repository for more information on how to deploy in production environments.

# Development Requirements
On Fedora >= 22:

    $ sudo dnf -y install php php-pdo mod_ssl httpd composer git php-phpunit-PHPUnit policycoreutils-python-utils

# Development Installation
*NOTE*: in the `chown` line you need to use your own user account name!

    $ cd /var/www
    $ sudo mkdir php-remote-storage
    $ sudo chown fkooman.fkooman php-remote-storage
    $ git clone https://github.com/fkooman/php-remote-storage.git
    $ cd php-remote-storage
    $ composer install
    $ mkdir -p data/storage
    $ sudo chown -R apache.apache data
    $ sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/php-remote-storage/data(/.*)?'
    $ sudo restorecon -R /var/www/php-remote-storage/data
    $ cp config/server.yaml.example config/server.yaml

Edit `config/server.yaml` to match the configuration. You need to at least 
modify the following lines, and set them to the values shown here:

    storageDir: /var/www/php-remote-storage/data/storage
   
    Db:
        dsn: 'sqlite:/var/www/php-remote-storage/data/rs.sqlite'

Now you can initialize the database (as the user running the web server):

    $ sudo -u apache bin/php-remote-storage-init

Copy paste the contents below in the file 
`/etc/httpd/conf.d/php-remote-storage.conf`:

    Alias /php-remote-storage /var/www/php-remote-storage/web

    <Directory /var/www/php-remote-storage/web>
        AllowOverride None

        Require local
        #Require all granted

        RewriteEngine On
        RewriteBase /php-remote-storage
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^(.*)$ index.php/$1 [QSA,L]

        SetEnvIfNoCase ^Authorization$ "(.+)" HTTP_AUTHORIZATION=$1
    </Directory>

Now restart Apache:

    $ sudo systemctl restart httpd

There are two default accounts available, `foo:bar` and `bar:baz`. You can
use them to login to the embedded OAuth server. The supported accounts and
their password can be changed using the `php-remote-storage-add-user` tool.

    $ php bin/php-remote-storage-add-user foobar mys3cr3t

If you ever remove the software, you can also remove the SELinux context:

    $ sudo semanage fcontext -d -t httpd_sys_rw_content_t '/var/www/php-remote-storage/data(/.*)?'

# WebFinger
In order to use this server you need to point a user's WebFinger information to 
this particular server.

You can use [php-webfinger](https://github.com/fkooman/php-webfinger) for this
if you want. It has example configuration files for remoteStorage. or use a 
static file at `https://example.org/.well-known/webfinger`. To work with this 
server, the following is an example indicating support for the `-03` and `-05` 
version of the specification:

    $ curl -k https://storage.tuxed.net/.well-known/webfinger?resource=acct:foo@storage.tuxed.net | python -mjson.tool
    {
        "links": [
            {
                "href": "https://storage.tuxed.net/php-remote-storage/foo",
                "properties": {
                    "http://remotestorage.io/spec/version": "draft-dejong-remotestorage-03",
                    "http://tools.ietf.org/html/rfc2616#section-14.16": false,
                    "http://tools.ietf.org/html/rfc6749#section-4.2": "https://storage.tuxed.net/php-remote-storage/authorize?login_hint=foo",
                    "http://tools.ietf.org/html/rfc6750#section-2.3": false
                },
                "rel": "remotestorage"
            },
            {
                "href": "https://storage.tuxed.net/php-remote-storage/foo",
                "properties": {
                    "http://remotestorage.io/spec/version": "draft-dejong-remotestorage-05",
                    "http://remotestorage.io/spec/web-authoring": null,
                    "http://tools.ietf.org/html/rfc6749#section-4.2": "https://storage.tuxed.net/php-remote-storage/authorize?login_hint=foo",
                    "http://tools.ietf.org/html/rfc6750#section-2.3": null,
                    "http://tools.ietf.org/html/rfc7233": null
                },
                "rel": "http://tools.ietf.org/id/draft-dejong-remotestorage"
            }
        ],
        "subject": "acct:foo@storage.tuxed.net"
    }

This WebFinger service is located at the same server as the storage server, but
that is not a requirement.

# Tests
You can run the included unit tests using PHPunit:

    $ cd /var/www/php-remote-storage
    $ phpunit

# remoteStorage API test suite

Some extra dependencies are needed to run the API test suite:

    $ sudo dnf -y install rubygem-bundler ruby-devel gcc-c++

Now install the test suite:

    $ mkdir $HOME/Projects
    $ cd $HOME/Projects
    $ git clone https://github.com/remotestorage/api-test-suite.git
    $ cd api-test-suite
    $ bundler install

You need to modify the configuration a bit to work with php-remote-storage:

    $ cp config.yml.example config.yml

Edit the `config.yml` file, e.g.:

    storage_base_url: http://localhost/php-remote-storage/demo
    storage_base_url_other: http://localhost/php-remote-storage/bar
    category: api-test
    token: token
    read_only_token: read_only_token
    root_token: root_token

Now, the Bearer token validation of php-remote-storage needs to be modified,
this will be configurable soon instead of a hack in the source. Modify
`/var/www/php-remote-storage/web/index.php` from:

    $apiAuth = new BearerAuthentication(
        new DbTokenValidator($db),
        array('realm' => 'remoteStorage API')
    );

To:

    $apiAuth = new BearerAuthentication(
        new fkooman\RemoteStorage\ApiTestTokenValidator(),
        array('realm' => 'remoteStorage API')
    );

Now you can run the test suite and all should be fine:

    $ rake test

# Contributing
You can send a pull request, ideally after first discussing a new feature or
fix. Please make sure there is an accompanying unit test for your feature or 
fix. I know the current test coverage is not perfect, but trying to improve :)

# License
Licensed under the GNU Affero General Public License as published by the Free
Software Foundation, either version 3 of the License, or (at your option) any
later version.

    https://www.gnu.org/licenses/agpl.html

This roughly means that if you use this software in your service you need to
make the source code available to the users of your service (if you modify
it). Refer to the license for the exact details.
