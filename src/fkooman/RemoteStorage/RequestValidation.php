<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Lesser General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Lesser General Public License for more details.
 *
 *  You should have received a copy of the GNU Lesser General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace fkooman\RemoteStorage;

use fkooman\Http\Request;
use fkooman\Http\Exception\BadRequestException;
use fkooman\OAuth\InputValidation;

class RequestValidation
{
    public static function validateDeleteApprovalRequest(Request $request)
    {
        // REQUIRED client_id
        $clientId = $request->getUrl()->getQueryParameter('client_id');
        if (is_null($clientId)) {
            throw new BadRequestException('missing client_id');
        }
        if (false === InputValidation::clientId($clientId)) {
            throw new BadRequestException('invalid client_id');
        }

        // REQUIRED response_type
        $responseType = $request->getUrl()->getQueryParameter('response_type');
        if (is_null($responseType)) {
            throw new BadRequestException('missing response_type');
        }
        if (false === InputValidation::responseType($responseType)) {
            throw new BadRequestException('invalid response_type');
        }

        // REQUIRED redirect_uri
        $redirectUri = $request->getUrl()->getQueryParameter('redirect_uri');
        if (is_null($redirectUri)) {
            throw new BadRequestException('missing redirect_uri');
        }
        if (false === InputValidation::redirectUri($redirectUri)) {
            throw new BadRequestException('invalid redirect_uri');
        }

        // REQUIRED scope
        $scope = $request->getUrl()->getQueryParameter('scope');
        if (is_null($scope)) {
            throw new BadRequestException('missing scope');
        }
        if (false === InputValidation::scope($scope)) {
            throw new BadRequestException('invalid scope');
        }

        return array(
            'client_id' => $clientId,
            'response_type' => $responseType,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
        );
    }
}
