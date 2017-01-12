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
use fkooman\RemoteStorage\OAuth\AuthorizationCode;
use fkooman\RemoteStorage\OAuth\AuthorizationCodeStorageInterface;

class TestAuthorizationCode implements AuthorizationCodeStorageInterface
{
    public function storeAuthorizationCode(AuthorizationCode $authorizationCode)
    {
        return Base64Url::encode(
            json_encode(
                [
                    'client_id' => $authorizationCode->getClientId(),
                    'user_id' => $authorizationCode->getUserId(),
                    'issued_at' => $authorizationCode->getIssuedAt(),
                    'redirect_uri' => $authorizationCode->getRedirectUri(),
                    'scope' => $authorizationCode->getScope(),
                ]
            )
        );
    }

    public function retrieveAuthorizationCode($authorizationCode)
    {
        $data = json_decode(
            Base64Url::decode($authorizationCode),
            true
        );

        return new AuthorizationCode(
            $data['client_id'],
            $data['user_id'],
            $data['issued_at'],
            $data['redirect_uri'],
            $data['scope']
        );
    }

    public function isFreshAuthorizationCode($authorizationCode)
    {
        if ('replayed_code' === $authorizationCode) {
            return false;
        }

        return true;
    }
}
