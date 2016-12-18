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
require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\Config\Reader;
use fkooman\Config\YamlFile;
use fkooman\OAuth\Storage\PdoAccessTokenStorage;
use fkooman\OAuth\Storage\PdoApprovalStorage;
use fkooman\OAuth\Storage\PdoAuthorizationCodeStorage;
use fkooman\RemoteStorage\MetadataStorage;

try {
    $configReader = new Reader(
        new YamlFile(
            dirname(__DIR__).'/config/server.yaml'
        )
    );

    $db = new PDO(
        $configReader->v('Db', 'dsn', false, sprintf('sqlite:%s/data/rs.sqlite', dirname(__DIR__))),
        $configReader->v('Db', 'username', false),
        $configReader->v('Db', 'password', false)
    );

    $metadataStorage = new MetadataStorage($db);
    $metadataStorage->initDatabase();

    $approvalStorage = new PdoApprovalStorage($db);
    $approvalStorage->initDatabase();

    $authorizationCodeStorage = new PdoAuthorizationCodeStorage($db);
    $authorizationCodeStorage->initDatabase();

    $accessTokenStorage = new PdoAccessTokenStorage($db);
    $accessTokenStorage->initDatabase();
} catch (Exception $e) {
    echo $e->getMessage().PHP_EOL;
    exit(1);
}
