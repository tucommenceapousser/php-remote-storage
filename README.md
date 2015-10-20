[![Build Status](https://travis-ci.org/fkooman/php-remote-storage.png?branch=master)](https://travis-ci.org/fkooman/php-remote-storage)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/fkooman/php-remote-storage/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/fkooman/php-remote-storage/?branch=master)

# Introduction
This is a remoteStorage server implementation written in PHP. It aims at 
implementing `draft-dejong-remotestorage-03.txt` and higher.

# Development Requirements
On Fedora >= 22:

    $ sudo dnf -y install php php-pdo mod_ssl httpd composer git php-phpunit-PHPUnit

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
    $ cp config/server.ini.example config/server.ini

Edit `config/server.ini` to match the configuration. You need to at least 
modify the following lines, and set them to the values shown here:

    storageDir = "/var/www/php-remote-storage/data/storage"
   
    [MetadataStorage]
    dsn = "sqlite:/var/www/php-remote-storage/data/metadata.sqlite"

    [TokenStorage]
    dsn = "sqlite:/var/www/php-remote-storage/data/token.sqlite"

Now you can initialize the database:

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

If you ever remove the software, you can also remove the SELinux context:

    $ sudo semanage fcontext -d -t httpd_sys_rw_content_t '/var/www/php-remote-storage/data(/.*)?'

# Tests
You can run the included unit tests using PHPunit:

    $ cd /var/www/php-remote-storage
    $ phpunit --verbose

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
