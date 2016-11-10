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

class RemoteStorageClientStorage implements ClientStorageInterface
{
    /**
     * Retrieve a client based on clientId, responseType, redirectUri and 
     * scope. The parameters except the clientId are optional and are used to
     * support non-registered clients.
     *
     * @param string      $clientId     the clientId
     * @param string|null $responseType the responseType or null
     * @param string|null $redirectUri  the redirectUri or null
     * @param string|null $scope        the scope or null
     *
     * @return Client|false if the client exists with the clientId it returns
     *                      Client, otherwise false
     */
    public function getClient($clientId, $responseType = null, $redirectUri = null, $scope = null)
    {
        $clientId = self::normalizeRedirectUriOrigin($redirectUri);

        return new Client($clientId, $responseType, $redirectUri, $scope, null);
    }

    private static function normalizeRedirectUriOrigin($redirectUri)
    {
        $scheme = strtolower(parse_url($redirectUri, PHP_URL_SCHEME));
        $host = strtolower(parse_url($redirectUri, PHP_URL_HOST));
        $port = parse_url($redirectUri, PHP_URL_PORT);

        $usePort = false;
        if (null !== $port) {
            if (443 !== $port && 'https' === $scheme) {
                $usePort = true;
            }
            if (80 !== $port && 'http' === $scheme) {
                $usePort = true;
            }
        }

        if ($usePort) {
            return sprintf('%s://%s:%d', $scheme, $host, $port);
        }

        return sprintf('%s://%s', $scheme, $host);
    }
}
