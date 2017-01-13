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

use fkooman\RemoteStorage\Config;
use fkooman\RemoteStorage\DocumentStorage;
use fkooman\RemoteStorage\Http\FormAuthenticationHook;
use fkooman\RemoteStorage\Http\FormAuthenticationModule;
use fkooman\RemoteStorage\Http\Request;
use fkooman\RemoteStorage\Http\Service;
use fkooman\RemoteStorage\Http\Session;
use fkooman\RemoteStorage\MetadataStorage;
use fkooman\RemoteStorage\OAuth\OAuthModule;
use fkooman\RemoteStorage\OAuth\TokenStorage;
use fkooman\RemoteStorage\Random;
use fkooman\RemoteStorage\RemoteStorage;
use fkooman\RemoteStorage\TwigTpl;
use fkooman\RemoteStorage\UiModule;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

$logger = new Logger('php-remote-storage');
$logger->pushHandler(new ErrorLogHandler());

try {
    $config = Config::fromFile(dirname(__DIR__).'/config/server.yaml');
    $serverMode = $config->getItem('serverMode');
    $document = new DocumentStorage(
        sprintf('%s/data/storage', dirname(__DIR__))
    );

    $templateCache = null;
    if ('development' !== $serverMode) {
        $templateCache = sprintf('%s/data/tpl', dirname(__DIR__));
    }

    $request = new Request($_SERVER, $_GET, $_POST);
    $templateManager = new TwigTpl(
        [
            dirname(__DIR__).'/views',
            dirname(__DIR__).'/config/views',
        ],
        $templateCache
    );
    $templateManager->setDefault(
        [
            'requestRoot' => $request->getRoot(),
            'serverMode' => $serverMode,
        ]
    );

    $db = new PDO(sprintf('sqlite:%s/data/rs.sqlite', dirname(__DIR__)));
    $md = new MetadataStorage($db, new Random());
    $md->initDatabase();

    $remoteStorage = new RemoteStorage($md, $document);
    $service = new Service();
    $service->setTpl($templateManager);

    $session = new Session(
        $request->getServerName(),
        $request->getRoot(),
        'development' !== $serverMode
    );

    $formAuthenticationHook = new FormAuthenticationHook(
        $session,
        $templateManager
    );
    $service->addBeforeHook('auth', $formAuthenticationHook);

    $service->addModule(
        new FormAuthenticationModule(
            $config->getSection('Users')->toArray(),
            $session,
            $templateManager
        )
    );

    $tokenStorage = new TokenStorage($db);
    $oauthModule = new OAuthModule(
        $templateManager,
        new Random(),
        $tokenStorage,
        $config
    );
    $service->addModule($oauthModule);

    $uiModule = new UiModule(
        $remoteStorage,
        $templateManager,
        $tokenStorage
    );
    $service->addModule($uiModule);

    $response = $service->run($request);

    if ('development' === $serverMode && !$response->isOkay()) {
        // log all non 2xx responses
        $logger->info(
            (string) $response
        );
    }
    $response->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    die(sprintf('ERROR: %s', $e->getMessage()));
}
