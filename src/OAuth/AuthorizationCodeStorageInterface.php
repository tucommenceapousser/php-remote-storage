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

namespace fkooman\RemoteStorage\OAuth;

interface AuthorizationCodeStorageInterface
{
    /**
     * Store an authorization code.
     *
     * @param AuthorizationCode $authorizationCode the authorization code to
     *                                             store
     *
     * @return string the authorization code that will be provided to the
     *                client
     */
    public function storeAuthorizationCode(AuthorizationCode $authorizationCode);

    /**
     * Retrieve an authorization code.
     *
     * @param string $authorizationCode the authorization code received from
     *                                  the client
     *
     * @return AuthorizationCode|false the authorization code object if the
     *                                 authorization code was found, or false
     *                                 if it was not found
     */
    public function retrieveAuthorizationCode($authorizationCode);

    /**
     * Check whether or not the authorization code was used before.
     *
     * @param string $authorizationCode the authorization code received from
     *                                  the client
     *
     * @return bool true if the code was not used before, false if it was used
     *              before. NOTE: a call to isFresh MUST mark that particular
     *              authorization code as used IMMEDIATELY. It must NEVER
     *              respond with true for the same authorization code.
     */
    public function isFreshAuthorizationCode($authorizationCode);
}
