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

interface AccessTokenStorageInterface
{
    /**
     * Store an access token.
     *
     * @param AccessToken $accessToken the access token to store
     *
     * @return string the access token that will be provided to the
     *                client
     */
    public function storeAccessToken(AccessToken $accessToken);

    /**
     * Retrieve an access token.
     *
     * @param string $accessToken the access token received from
     *                            the resource server
     *
     * @return AccessToken|false the access token object if the
     *                           access token was found, or false if it was
     *                           not found
     */
    public function retrieveAccessToken($accessToken);
}
