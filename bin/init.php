#!/usr/bin/php
<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Lesser General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Lesser General Public License for more details.
 *
 *  You should have received a copy of the GNU Lesser General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use fkooman\RemoteStorage\Config;
use fkooman\RemoteStorage\MetadataStorage;
use fkooman\RemoteStorage\OAuth\TokenStorage;
use fkooman\RemoteStorage\Random;

try {
    $config = Config::fromFile(dirname(__DIR__).'/config/server.yaml');
    $db = new PDO(sprintf('sqlite:%s/data/rs.sqlite', dirname(__DIR__)));

    $metadataStorage = new MetadataStorage($db, new Random());
    $metadataStorage->initDatabase();

    $tokenStorage = new TokenStorage($db);
    $tokenStorage->init();
} catch (Exception $e) {
    echo $e->getMessage().PHP_EOL;
    exit(1);
}
