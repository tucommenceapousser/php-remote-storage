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

namespace fkooman\RemoteStorage\OAuth\Test;

use fkooman\RemoteStorage\OAuth\Client;
use fkooman\RemoteStorage\OAuth\ClientStorageInterface;

class TestClient implements ClientStorageInterface
{
    public function getClient($clientId, $responseType = null, $redirectUri = null, $scope = null)
    {
        // XXX do something if the redirect_uri and scope are not matching...
        if ('test-client' === $clientId) {
            return new Client(
                $clientId,
                'code',
                'https://localhost/cb',
                'post',
                '$2y$10$l.ebSWe5xsSBKaaUqisVFetaIiGfjU.tnYjjL/izt95Rr5LNSYH4q'
            );
        }

        // XXX do something if the redirect_uri and scope are not matching...
        if ('test-anonymous-client' === $clientId && 'code' === $responseType) {
            return new Client(
                $clientId,
                'code',
                'https://localhost/cb',
                'post',
                null   // no secret set
            );
        }

        if ('test-token-client' === $clientId) {
            return new Client(
                $clientId,
                'token',
                'https://localhost/cb',
                'post',
                null   // no secret set
            );
        }

        // not registered
        return false;
    }
}
