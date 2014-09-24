[![Build Status](https://travis-ci.org/fkooman/php-remote-storage.png?branch=master)](https://travis-ci.org/fkooman/php-remote-storage)

# Introduction
This is a remoteStorage server implementation written in PHP. It aims at 
implementing `draft-dejong-remotestorage-03.txt`.

# License
Licensed under the GNU Affero General Public License as published by the Free
Software Foundation, either version 3 of the License, or (at your option) any
later version.

    https://www.gnu.org/licenses/agpl.html

This roughly means that if you use this software in your service you need to
make the source code available to the users of your service (if you modify
it). Refer to the license for the exact details.

# Installation
Please use the RPM packages for actually running it on a server. The RPM 
packages can for now be found in the 
[repository](https://repos.fedoraproject.org/repo/fkooman/php-oauth). For 
setting up a development environment, see below.

# Docker
It is possible to use Docker to evaluate this server, see the `docker` folder
in this repository.

# Development Requirements
On Fedora/CentOS:

    $ sudo yum install php-pdo php-openssl httpd'

You also need to download [Composer](https://getcomposer.org/).

The software is being tested with Fedora 20 and CentOS 7 and should also work 
on and RHEL 7.

**NOTE**: PHP 5.4 or higher is required.

# Development Installation
*NOTE*: in the `chown` line you need to use your own user account name!

    $ cd /var/www
    $ sudo mkdir php-remote-storage
    $ sudo chown fkooman.fkooman php-remote-storage
    $ git clone https://github.com/fkooman/php-remote-storage.git
    $ cd php-remote-storage
    $ /path/to/composer.phar install
    $ mkdir -p data/storage
    $ sudo chown -R apache.apache data
    $ sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/php-remote-storage/data(/.*)?'
    $ sudo restorecon -R /var/www/php-remote-storage/data
    $ cd config
    $ cp rs.ini.defaults rs.ini

Edit `rs.ini` to match the configuration. You need to at least modify the
following lines, and set them to the values shown here:

    storageDir = "/var/www/php-remote-storage/data/storage"
    dsn = "sqlite:/var/www/php-remote-storage/data/metadata.sqlite"

Now you can initialize the metadata database:

    $ sudo -u apache bin/php-remote-storage-initdb 

Copy paste the contents of the Apache section (see below) in the file 
`/etc/httpd/conf.d/php-remote-storage.conf`.

    $ sudo service httpd restart

If you ever remove the software, you can also remove the SELinux context:

    $ sudo semanage fcontext -d -t httpd_sys_rw_content_t '/var/www/php-remote-storage/data(/.*)?'

# Apache
This is the Apache configuration you use for development. Place it in 
`/etc/httpd/conf.d/php-remote-storage.conf` and don't forget to restart Apache:

    Alias /php-remote-storage /var/www/php-remote-storage/web

    <Directory /var/www/php-remote-storage/web>
        AllowOverride None
        Options FollowSymLinks

        <IfModule mod_authz_core.c>
          # Apache 2.4
          Require local
        </IfModule>
        <IfModule !mod_authz_core.c>
          # Apache 2.2
          Order Deny,Allow
          Deny from All
          Allow from 127.0.0.1
          Allow from ::1
        </IfModule>

        RewriteEngine On
        RewriteCond %{HTTP:Authorization} ^(.+)$
        RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

        # HSTS: https://developer.mozilla.org/en-US/docs/Security/HTTP_Strict_Transport_Security
        #Header set Strict-Transport-Security max-age=604800
    </Directory>

