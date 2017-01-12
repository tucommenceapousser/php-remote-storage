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

class Approval
{
    /** @var string */
    private $userId;

    /** @var string */
    private $clientId;

    /** @var string */
    private $responseType;

    /** @var string */
    private $scope;

    public function __construct($userId, $clientId, $responseType, $scope)
    {
        $this->userId = $userId;
        $this->clientId = $clientId;
        $this->responseType = $responseType;
        $this->scope = $scope;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    public function getResponseType()
    {
        return $this->responseType;
    }

    public function getScope()
    {
        return $this->scope;
    }
}
