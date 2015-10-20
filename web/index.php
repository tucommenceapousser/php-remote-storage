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
use fkooman\RemoteStorage\DbTokenValidator;
use fkooman\RemoteStorage\RemoteStorage;
use fkooman\RemoteStorage\RemoteStorageResourceServer;
use fkooman\RemoteStorage\RemoteStorageService;
use fkooman\RemoteStorage\MetadataStorage;
use fkooman\RemoteStorage\DocumentStorage;
use fkooman\Tpl\Twig\TwigTemplateManager;
use fkooman\OAuth\OAuthServer;
use fkooman\OAuth\Storage\UnregisteredClientStorage;
use fkooman\OAuth\Storage\PdoCodeTokenStorage;
use fkooman\Rest\Plugin\Authentication\Basic\BasicAuthentication;
use fkooman\Http\Request;

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

$userAuth = new BasicAuthentication(
    function ($userId) use ($iniReader) {
        $userList = $iniReader->v('BasicAuthentication');
        if (!array_key_exists($userId, $userList)) {
            return false;
        }

        return $userList[$userId];
    },
    array('realm' => 'OAuth AS')
);

$templateManager = new TwigTemplateManager(
    array(
        dirname(__DIR__).'/views',
        dirname(__DIR__).'/config/views',
    ),
    null
);

// DB
$db = new PDO(
    $iniReader->v('TokenStorage', 'dsn'),
    $iniReader->v('TokenStorage', 'username', false),
    $iniReader->v('TokenStorage', 'password', false)
);
$pdoCodeTokenStorage = new PdoCodeTokenStorage($db);

$server = new OAuthServer(
    $templateManager,
    new UnregisteredClientStorage(),    // we do not have client registration
    new RemoteStorageResourceServer(),  // we only have one resource server
    $pdoCodeTokenStorage,               // we do not have codes, only...
    $pdoCodeTokenStorage                // ...tokens
);

$apiAuth = new BearerAuthentication(
    new DbTokenValidator($db),
    array('realm' => 'remoteStorage API')
);

$service = new RemoteStorageService(
    $server,
    $remoteStorage,
    $userAuth,
    $apiAuth,
    array(
        'disable_token_endpoint',
        'disable_introspect_endpoint',
    )
);

$request = new Request($_SERVER);
$service->run($request)->send();
