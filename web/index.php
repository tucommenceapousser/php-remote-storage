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
use fkooman\RemoteStorage\Http\Controller;
use fkooman\RemoteStorage\Http\Request;
use fkooman\RemoteStorage\Http\Session;
use fkooman\RemoteStorage\MetadataStorage;
use fkooman\RemoteStorage\OAuth\TokenStorage;
use fkooman\RemoteStorage\Random;
use fkooman\RemoteStorage\RemoteStorage;
use fkooman\RemoteStorage\TwigTpl;

try {
    $config = Config::fromFile(dirname(__DIR__).'/config/server.yaml');
    $serverMode = $config->getItem('serverMode');
    $document = new DocumentStorage(
        sprintf('%s/data/storage', dirname(__DIR__))
    );

    $request = new Request($_SERVER, $_GET, $_POST, file_get_contents('php://input'));

    $templateCache = null;
    if ('development' !== $serverMode) {
        $templateCache = sprintf('%s/data/tpl', dirname(__DIR__));
    }
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

    $session = new Session(
        $request->getServerName(),
        $request->getRoot(),
        'development' !== $serverMode
    );

    $tokenStorage = new TokenStorage($db);

    $controller = new Controller(
        $templateManager,
        $session,
        $tokenStorage,
        new Random(),
        $remoteStorage,
        $config->getSection('Users')->toArray()
    );
    $response = $controller->run($request);

    if ('development' === $serverMode && !$response->isOkay()) {
        // log all non 2xx responses
        // log all non 2xx responses
        error_log((string) $response);
    }
    $response->send();
} catch (Exception $e) {
    error_log($e->getMessage());
    die(sprintf('ERROR: %s', $e->getMessage()));
}
