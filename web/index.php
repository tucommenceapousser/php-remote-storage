<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\Ini\IniReader;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Authentication\Bearer\IntrospectionUserPassValidator;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\RemoteStorage\RemoteStorage;
use fkooman\RemoteStorage\RemoteStorageService;
use fkooman\RemoteStorage\MetadataStorage;
use fkooman\RemoteStorage\DocumentStorage;

$iniReader = IniReader::fromFile(
    dirname(__DIR__).'/config/server.ini'
);

$md = new MetadataStorage(
    new PDO(
        $iniReader->v('MetadataStorage', 'dsn'),
        $iniReader->v('MetadataStorage', 'username', false),
        $iniReader->v('MetadataStorage', 'password', false)
    )
);

$document = new DocumentStorage(
    $iniReader->v('storageDir')
);

$remoteStorage = new RemoteStorage($md, $document);

$bearerAuth = new BearerAuthentication(
    new IntrospectionUserPassValidator(
        $iniReader->v('tokenIntrospectionUri'),
        'foo',
        'bar'
    )
);

$service = new RemoteStorageService($remoteStorage);
$authenticationPlugin = new AuthenticationPlugin();
$authenticationPlugin->register($bearerAuth, 'token');
$service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
$service->run()->send();
