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

use InvalidArgumentException;

class Client
{
    /** @var string */
    private $clientId;

    /** @var string */
    private $responseType;

    /** @var string */
    private $redirectUri;

    /** @var string */
    private $scope;

    /** @var string */
    private $secret;

    public function __construct($clientId, $responseType, $redirectUri, $scope, $secret)
    {
        $this->setClientId($clientId);
        $this->setResponseType($responseType);
        $this->setRedirectUri($redirectUri);
        $this->setScope($scope);
        $this->setSecret($secret);
    }

    public function setClientId($clientId)
    {
        if (false === InputValidation::clientId($clientId)) {
            throw new InvalidArgumentException('invalid client_id');
        }
        $this->clientId = $clientId;
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    public function setResponseType($responseType)
    {
        if (false === InputValidation::responseType($responseType)) {
            throw new InvalidArgumentException('invalid response_type');
        }
        $this->responseType = $responseType;
    }

    public function getResponseType()
    {
        return $this->responseType;
    }

    public function setRedirectUri($redirectUri)
    {
        if (false === InputValidation::redirectUri($redirectUri)) {
            throw new InvalidArgumentException('invalid redirect_uri');
        }
        $this->redirectUri = $redirectUri;
    }

    public function getRedirectUri()
    {
        return $this->redirectUri;
    }

    public function setScope($scope)
    {
        if (false === InputValidation::scope($scope)) {
            throw new InvalidArgumentException('invalid scope');
        }
        $this->scope = $scope;
    }

    public function getScope()
    {
        return $this->scope;
    }

    public function setSecret($secret)
    {
        //        // XXX: validate secret as well
//        if(false === InputValidation::secret($secret)) {
//            throw new InvalidArgumentException('invalid secret');
//        }
        $this->secret = $secret;
    }

    public function getSecret()
    {
        return $this->secret;
    }
}
