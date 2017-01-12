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
use fkooman\RemoteStorage\ApprovalManagementStorage;
use fkooman\RemoteStorage\Config\Reader;
use fkooman\RemoteStorage\Config\YamlFile;
use fkooman\RemoteStorage\DbTokenValidator;
use fkooman\RemoteStorage\DocumentStorage;
use fkooman\RemoteStorage\MetadataStorage;
use fkooman\RemoteStorage\OAuth\Storage\PdoAccessTokenStorage;
use fkooman\RemoteStorage\OAuth\Storage\PdoApprovalStorage;
use fkooman\RemoteStorage\OAuth\Storage\PdoAuthorizationCodeStorage;
use fkooman\RemoteStorage\RemoteStorage;
use fkooman\RemoteStorage\RemoteStorageClientStorage;
use fkooman\RemoteStorage\RemoteStorageResourceServer;
use fkooman\RemoteStorage\RemoteStorageService;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Authentication\Form\FormAuthentication;
use fkooman\Tpl\Twig\TwigTemplateManager;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

$logger = new Logger('php-remote-storage');
$logger->pushHandler(new ErrorLogHandler());

try {
    $request = new Request($_SERVER);

    $configReader = new Reader(
        new YamlFile(
            dirname(__DIR__).'/config/server.yaml'
        )
    );

    $serverMode = $configReader->v('serverMode', false, 'production');

    $document = new DocumentStorage(
        $configReader->v('storageDir', false, sprintf('%s/data/storage', dirname(__DIR__)))
    );

    $dbDsn = $configReader->v('Db', 'dsn', false, sprintf('sqlite:%s/data/rs.sqlite', dirname(__DIR__)));
    // if we use sqlite, and database is not initialized we will initialize
    // all tables here. No need to manually initialize the database then!
    $initDb = false;
    if (0 === strpos($dbDsn, 'sqlite:')) {
        // sqlite
        if (!file_exists(substr($dbDsn, 7))) {
            // sqlite file does not exist
            $initDb = true;
        }
    }

    $db = new PDO(
        $dbDsn,
        $configReader->v('Db', 'username', false),
        $configReader->v('Db', 'password', false)
    );

    // only enable templateCache when in production mode
    if ('development' !== $serverMode) {
        $templateCache = $configReader->v('templateCache', false, sprintf('%s/data/tpl', dirname(__DIR__)));
    } else {
        $templateCache = null;
    }

    $templateManager = new TwigTemplateManager(
        [
            dirname(__DIR__).'/views',
            dirname(__DIR__).'/config/views',
        ],
        $templateCache
    );
    $templateManager->setDefault(
        [
            'rootFolder' => $request->getUrl()->getRoot(),
            'serverMode' => $serverMode,
        ]
    );

    $md = new MetadataStorage($db);
    $approvalStorage = new PdoApprovalStorage($db);
    $authorizationCodeStorage = new PdoAuthorizationCodeStorage($db);
    $accessTokenStorage = new PdoAccessTokenStorage($db);

    if ($initDb) {
        $md->initDatabase();
        $approvalStorage->initDatabase();
        $authorizationCodeStorage->initDatabase();
        $accessTokenStorage->initDatabase();
    }

    $remoteStorage = new RemoteStorage($md, $document);

    $session = new Session(
        'php-remote-storage',
        [
            'secure' => 'development' !== $serverMode,
        ]
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
        $session,
        $logger
    );

    $apiAuth = new BearerAuthentication(
        new DbTokenValidator($db),
    //    new fkooman\RemoteStorage\ApiTestTokenValidator(),
        [
            'realm' => 'remoteStorage',
        ],
        $logger
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
        [
            'disable_token_endpoint' => true,
            'disable_introspect_endpoint' => true,
            'route_prefix' => '/_oauth',
            'require_state' => false,
            'server_mode' => $serverMode,
        ]
    );
    $service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);

    $response = $service->run($request);
    if ('development' === $serverMode && !$response->isOkay()) {
        // log all non 2xx responses
        $logger->info(
            var_export(
                $response->toArray(),
                true
            )
        );
    }
    $response->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    die(sprintf('ERROR: %s', $e->getMessage()));
}
