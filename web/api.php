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

use fkooman\Ini\IniReader;
use fkooman\Rest\Plugin\Bearer\BearerAuthentication;
use fkooman\RemoteStorage\RemoteStorage;
use fkooman\RemoteStorage\RemoteStorageService;
use fkooman\RemoteStorage\MetadataStorage;
use fkooman\RemoteStorage\DocumentStorage;
use fkooman\Http\Exception\HttpException;
use fkooman\Http\Exception\InternalServerErrorException;

try {
    $iniReader = IniReader::fromFile(
        dirname(__DIR__).'/config/rs.ini'
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

    $bearerAuthentication = new BearerAuthentication($iniReader->v('tokenIntrospectionUri'), 'remoteStorage');

    $remoteStorage = new RemoteStorage($md, $document);
    $remoteStorageService = new RemoteStorageService($remoteStorage);
    $remoteStorageService->registerBeforeEachMatchPlugin($bearerAuthentication);
    $remoteStorageService->run()->sendResponse();
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
