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

use fkooman\RemoteStorage\Base64\Base64Url;
use fkooman\RemoteStorage\OAuth\AccessToken;
use fkooman\RemoteStorage\OAuth\AccessTokenStorageInterface;

class TestAccessToken implements AccessTokenStorageInterface
{
    public function storeAccessToken(AccessToken $accessToken)
    {
        return Base64Url::encode(
             json_encode(
                [
                    'client_id' => $accessToken->getClientId(),
                    'user_id' => $accessToken->getUserId(),
                    'issued_at' => $accessToken->getIssuedAt(),
                    'scope' => $accessToken->getScope(),
                ]
            )
        );
    }

    public function retrieveAccessToken($accessToken)
    {
        $data = json_decode(
            Base64Url::decode($accessToken),
            true
        );

        return new AccessToken(
            $data['client_id'],
            $data['user_id'],
            $data['issued_at'],
            $data['scope']
        );
    }
}
