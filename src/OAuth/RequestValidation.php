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

use fkooman\Http\Exception\BadRequestException;
use fkooman\Http\Request;

class RequestValidation
{
    public static function validateAuthorizeRequest(Request $request, $requireState = true)
    {
        // REQUIRED client_id
        $clientId = self::validateParameter(
            $request->getUrl()->getQueryParameter('client_id'),
            'clientId'
        );
        // REQUIRED response_type
        $responseType = self::validateParameter(
            $request->getUrl()->getQueryParameter('response_type'),
            'responseType'
        );
        // REQUIRED redirect_uri
        $redirectUri = self::validateParameter(
            $request->getUrl()->getQueryParameter('redirect_uri'),
            'redirectUri'
        );
        // REQUIRED scope
        $scope = self::validateParameter(
            $request->getUrl()->getQueryParameter('scope'),
            'scope'
        );
        // REQUIRED state (but allow override with flag)
        $state = self::validateParameter(
            $request->getUrl()->getQueryParameter('state'),
            'state',
            $requireState
        );
        if (is_null($state)) {
            $state = 'xxx_client_should_set_state_xxx';
        }

        return [
            'client_id' => $clientId,
            'response_type' => $responseType,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'state' => $state,
        ];
    }

    public static function validatePostAuthorizeRequest(Request $request, $requireState = true)
    {
        $requestData = self::validateAuthorizeRequest($request, $requireState);

        // REQUIRED approval
        $approval = self::validateParameter($request->getPostParameter('approval'), 'approval');

        $requestData['approval'] = $approval;

        return $requestData;
    }

    public static function validateTokenRequest(Request $request)
    {
        // REQUIRED grant_type
        $grantType = self::validateParameter($request->getPostParameter('grant_type'), 'grantType');
        // REQUIRED client_id
        $clientId = self::validateParameter($request->getPostParameter('client_id'), 'clientId');
        // REQUIRED code
        $code = self::validateParameter($request->getPostParameter('code'), 'code');
        // REQUIRED redirect_uri
        $redirectUri = self::validateParameter($request->getPostParameter('redirect_uri'), 'redirectUri');

        return [
            'grant_type' => $grantType,
            'client_id' => $clientId,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];
    }

    public static function validateIntrospectRequest(Request $request)
    {
        // REQUIRED token
        $token = self::validateParameter($request->getPostParameter('token'), 'token');

        return [
            'token' => $token,
        ];
    }

    public static function validateParameter($parameterValue, $validatorMethod, $isRequired = true)
    {
        if (is_null($parameterValue)) {
            if ($isRequired) {
                throw new BadRequestException(sprintf('missing %s', $validatorMethod));
            }

            return;
        }

        if (false === InputValidation::$validatorMethod($parameterValue)) {
            throw new BadRequestException(sprintf('invalid %s', $validatorMethod));
        }

        return $parameterValue;
    }
}
