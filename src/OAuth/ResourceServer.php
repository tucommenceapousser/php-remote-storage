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

class ResourceServer
{
    /** @var string */
    private $resourceServerId;

    /** @var string */
    private $scope;

    /** @var string */
    private $secret;

    public function __construct($resourceServerId, $scope, $secret)
    {
        $this->resourceServerId = $resourceServerId;
        $this->scope = $scope;
        $this->secret = $secret;
    }

    public function getResourceServerId()
    {
        return $this->resourceServerId;
    }

    public function getScope()
    {
        return $this->scope;
    }

    public function getSecret()
    {
        return $this->secret;
    }
}
