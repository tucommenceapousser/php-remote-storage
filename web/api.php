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

require_once dirname(__DIR__)."/vendor/autoload.php";

use fkooman\Config\Config;
use fkooman\Http\Request;
use fkooman\Http\IncomingRequest;
use fkooman\RemoteStorage\RemoteStorage;
use fkooman\RemoteStorage\RemoteStorageRequestHandler;
use fkooman\RemoteStorage\MetadataStorage;
use fkooman\RemoteStorage\DocumentStorage;
use fkooman\OAuth\ResourceServer\ResourceServer;
use fkooman\Http\Exception\HttpException;
use fkooman\Http\Exception\InternalServerErrorException;
use Guzzle\Http\Client;

try {
    $config = Config::fromIniFile(
        dirname(__DIR__)."/config/rs.ini"
    );

    $md = new MetadataStorage(
        new PDO(
            $config->s('MetadataStorage')->l('dsn'),
            $config->s('PdoStorage')->l('username', false),
            $config->s('PdoStorage')->l('password', false)
        )
    );

    $document = new DocumentStorage(
        $config->l('storageDir', true)
    );

    $resourceServer = new ResourceServer(
        new Client(
            $config->l('tokenIntrospectionUri', true)
        )
    );

    $request = Request::fromIncomingRequest(new IncomingRequest());

    $remoteStorage = new RemoteStorage($md, $document);
    $remoteStorageRequestHandler = new RemoteStorageRequestHandler($remoteStorage, $resourceServer);
    $response = $remoteStorageRequestHandler->handleRequest($request);
    $response->sendResponse();
} catch (Exception $e) {
    if ($e instanceof HttpException) {
        $response = $e->getResponse();
    } else {
        // we catch all other (unexpected) exceptions and return a 500
        $e = new InternalServerErrorException($e->getMessage());
        $response = $e->getResponse();
    }
    // FIXME: add CORS
    $response->sendResponse();
}
