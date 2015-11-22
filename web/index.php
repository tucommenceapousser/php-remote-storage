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

use fkooman\Http\Request;
use fkooman\Http\Session;
use fkooman\Config\YamlFile;
use fkooman\Config\Reader;
use fkooman\OAuth\Storage\PdoAccessTokenStorage;
use fkooman\OAuth\Storage\PdoAuthorizationCodeStorage;
use fkooman\OAuth\Storage\PdoApprovalStorage;
use fkooman\RemoteStorage\RemoteStorageClientStorage;
use fkooman\RemoteStorage\DbTokenValidator;
use fkooman\RemoteStorage\DocumentStorage;
use fkooman\RemoteStorage\MetadataStorage;
use fkooman\RemoteStorage\RemoteStorage;
use fkooman\RemoteStorage\RemoteStorageResourceServer;
use fkooman\RemoteStorage\RemoteStorageService;
use fkooman\RemoteStorage\ApprovalManagementStorage;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Authentication\Form\FormAuthentication;
use fkooman\Tpl\Twig\TwigTemplateManager;

$configReader = new Reader(
    new YamlFile(
        dirname(__DIR__).'/config/server.yaml'
    )
);

$serverMode = $configReader->v('serverMode', false, 'production');

$db = new PDO(
    $configReader->v('Db', 'dsn'),
    $configReader->v('Db', 'username', false),
    $configReader->v('Db', 'password', false)
);

$request = new Request($_SERVER);

$templateManager = new TwigTemplateManager(
    array(
        dirname(__DIR__).'/views',
        dirname(__DIR__).'/config/views',
    ),
    $configReader->v('templateCache', false, null)
);
$templateManager->setDefault(
    array(
        'rootFolder' => $request->getUrl()->getRoot(),
        'serverMode' => $serverMode,
    )
);

$approvalStorage = new PdoApprovalStorage($db);
$authorizationCodeStorage = new PdoAuthorizationCodeStorage($db);
$accessTokenStorage = new PdoAccessTokenStorage($db);

$md = new MetadataStorage($db);
$document = new DocumentStorage(
    $configReader->v('storageDir')
);

$remoteStorage = new RemoteStorage($md, $document);

$session = new Session(
    'php-remote-storage',
    array(
        'secure' => 'development' !== $serverMode,
    )
);

$userAuth = new FormAuthentication(
    function ($userId) use ($configReader) {
        $userList = $configReader->v('Users');
        if (null === $userList || !array_key_exists($userId, $userList)) {
            return false;
        }

        return $userList[$userId];
    },
    $templateManager,
    $session
);

$apiAuth = new BearerAuthentication(
    new DbTokenValidator($db),
#    new fkooman\RemoteStorage\ApiTestTokenValidator(),
    array(
        'realm' => 'remoteStorage',
    )
);

$authenticationPlugin = new AuthenticationPlugin();
$authenticationPlugin->register($userAuth, 'user');
$authenticationPlugin->register($apiAuth, 'api');

$service = new RemoteStorageService(
    $remoteStorage,
    new ApprovalManagementStorage($db),
    $templateManager,
    new RemoteStorageClientStorage(),
    new RemoteStorageResourceServer(),
    $approvalStorage,
    $authorizationCodeStorage,
    $accessTokenStorage,
    array(
        'disable_token_endpoint' => true,
        'disable_introspect_endpoint' => true,
        'route_prefix' => '/_oauth',
        'require_state' => false,
        'server_mode' => $serverMode,
    )
);
$service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);

$service->run($request)->send();
