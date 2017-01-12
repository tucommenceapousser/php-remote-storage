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

namespace fkooman\RemoteStorage\OAuth\Storage;

use fkooman\RemoteStorage\OAuth\Client;
use fkooman\RemoteStorage\OAuth\ClientStorageInterface;

class ArrayClientStorage implements ClientStorageInterface
{
    /** @var array */
    private $clientConfig;

    public function __construct(array $clientConfig)
    {
        $this->clientConfig = $clientConfig;
    }

    public function getClient($clientId, $responseType = null, $redirectUri = null, $scope = null)
    {
        if (!array_key_exists($clientId, $this->clientConfig)) {
            return false;
        }

        // secret is not always needed
        $clientSecret = array_key_exists('secret', $this->clientConfig[$clientId]) ? $this->clientConfig[$clientId]['secret'] : null;

        return new Client(
            $clientId,
            $this->clientConfig[$clientId]['response_type'],
            $this->clientConfig[$clientId]['redirect_uri'],
            $this->clientConfig[$clientId]['scope'],
            $clientSecret
        );
    }
}
