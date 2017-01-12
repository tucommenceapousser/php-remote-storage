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

use fkooman\RemoteStorage\OAuth\AuthorizationCode;
use fkooman\RemoteStorage\OAuth\AuthorizationCodeStorageInterface;

/**
 * If only the implicit grant profile is supported we do not need authorization
 * codes.
 */
class NullAuthorizationCodeStorage implements AuthorizationCodeStorageInterface
{
    public function storeAuthorizationCode(AuthorizationCode $authorizationCode)
    {
        // NOP
    }

    public function retrieveAuthorizationCode($authorizationCode)
    {
        return false;
    }

    public function isFreshAuthorizationCode($authorizationCode)
    {
        return false;
    }
}
