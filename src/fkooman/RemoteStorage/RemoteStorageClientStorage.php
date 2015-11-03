<?php

/**
 *  Copyright 2015 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace fkooman\RemoteStorage;

use fkooman\OAuth\ClientStorageInterface;
use fkooman\OAuth\Client;
use fkooman\Http\Exception\BadRequestException;

class RemoteStorageClientStorage implements ClientStorageInterface
{
    public function getClient($clientId, $responseType = null, $redirectUri = null, $scope = null)
    {
        // we know redirect_uri is a valid URI, now we must make sure that
        // redirect_uri starts with the value of client_id (the Origin)
        if (0 !== strpos($redirectUri, $clientId)) {
            throw new BadRequestException('client_id MUST be same origin as redirect_uri');
        }

        return new Client($clientId, $responseType, $redirectUri, $scope, null);
    }
}
