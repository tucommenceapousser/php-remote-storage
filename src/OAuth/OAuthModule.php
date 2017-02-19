<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace fkooman\RemoteStorage\OAuth;

use DateInterval;
use DateTime;
use fkooman\RemoteStorage\Http\Exception\HttpException;
use fkooman\RemoteStorage\Http\HtmlResponse;
use fkooman\RemoteStorage\Http\RedirectResponse;
use fkooman\RemoteStorage\Http\Request;
use fkooman\RemoteStorage\RandomInterface;
use fkooman\RemoteStorage\TplInterface;

class OAuthModule
{
    /** @var \fkooman\RemoteStorage\TplInterface */
    private $tpl;

    /** @var \fkooman\RemoteStorage\RandomInterface */
    private $random;

    /** @var TokenStorage */
    private $tokenStorage;

    /** @var \DateTime */
    private $dateTime;

    /** @var int */
    private $expiresIn = 7776000;   /* 90 days */

    public function __construct(TplInterface $tpl, TokenStorage $tokenStorage, RandomInterface $random, DateTime $dateTime)
    {
        $this->tpl = $tpl;
        $this->tokenStorage = $tokenStorage;
        $this->random = $random;
        $this->dateTime = $dateTime;
    }

    /**
     * @param int $expiresIn
     */
    public function setExpiresIn($expiresIn)
    {
        $this->expiresIn = (int) $expiresIn;
    }

    public function getAuthorize(Request $request, $userId)
    {
        $this->validateRequest($request);
        $this->validateClient($request);

        // ask for approving this client/scope
        return new HtmlResponse(
            $this->tpl->render(
                'authorizeOAuthClient',
                [
                    'client_id' => $request->getQueryParameter('client_id'),
                    'scope' => $request->getQueryParameter('scope'),
                    'redirect_uri' => $request->getQueryParameter('redirect_uri'),
                ]
            )
        );
    }

    public function postAuthorize(Request $request, $userId)
    {
        $this->validateRequest($request);
        $this->validateClient($request);

        // state is OPTIONAL in remoteStorage specification
        $state = $request->getQueryParameter('state', false, null);
        $returnUriPattern = '%s#%s';

        if ('no' === $request->getPostParameter('approve')) {
            $redirectParameters = [
                'error' => 'access_denied',
                'error_description' => 'user refused authorization',
            ];
            if (!is_null($state)) {
                $redirectParameters['state'] = $state;
            }
            $redirectQuery = http_build_query($redirectParameters);

            $redirectUri = sprintf($returnUriPattern, $request->getQueryParameter('redirect_uri'), $redirectQuery);

            return new RedirectResponse($redirectUri, 302);
        }

        $accessToken = $this->getAccessToken(
            $userId,
            $request->getQueryParameter('client_id'),
            $request->getQueryParameter('scope')
        );

        // add access_token, expires_in (and optionally state) to redirect_uri
        $redirectParameters = [
            'access_token' => $accessToken,
            'expires_in' => $this->expiresIn,
        ];
        if (!is_null($state)) {
            $redirectParameters['state'] = $state;
        }
        $redirectQuery = http_build_query($redirectParameters);
        $redirectUri = sprintf($returnUriPattern, $request->getQueryParameter('redirect_uri'), $redirectQuery);

        return new RedirectResponse($redirectUri, 302);
    }

    private function getAccessToken($userId, $clientId, $scope)
    {
        $existingToken = $this->tokenStorage->getExistingToken(
            $userId,
            $clientId,
            $scope
        );

        if (false !== $existingToken && $this->dateTime < new DateTime($existingToken['expires_at'])) {
            // if the user already has an access_token for this client and
            // scope, reuse it
            $accessTokenKey = $existingToken['access_token_key'];
            $accessToken = $existingToken['access_token'];
        } else {
            // generate a new one
            $accessTokenKey = $this->random->get(16);
            $accessToken = $this->random->get(16);
            $expiresAt = date_add(clone $this->dateTime, new DateInterval(sprintf('PT%dS', $this->expiresIn)));

            // store it
            $this->tokenStorage->store(
                $userId,
                $accessTokenKey,
                $accessToken,
                $clientId,
                $scope,
                $expiresAt
            );
        }

        return sprintf('%s.%s', $accessTokenKey, $accessToken);
    }

    private function validateRequest(Request $request)
    {
        // we enforce that all parameter are set, nothing is "OPTIONAL"
        $clientId = $request->getQueryParameter('client_id');
        if (1 !== preg_match('/^[\x20-\x7E]+$/', $clientId)) {
            throw new HttpException('invalid client_id', 400);
        }

        // XXX we also should enforce HTTPS
        $redirectUri = $request->getQueryParameter('redirect_uri');
        if (false === filter_var($redirectUri, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED | FILTER_FLAG_PATH_REQUIRED)) {
            throw new HttpException('invalid redirect_uri', 400);
        }
        if (false !== strpos($redirectUri, '?')) {
            throw new HttpException('invalid redirect_uri', 400);
        }
        $responseType = $request->getQueryParameter('response_type');
        if ('token' !== $responseType) {
            throw new HttpException('invalid response_type', 400);
        }
        // XXX make sure this regexp/code is actually correct!
        $scope = $request->getQueryParameter('scope');
        $scopeTokens = explode(' ', $scope);
        foreach ($scopeTokens as $scopeToken) {
            if (1 !== preg_match('/^[\x21\x23-\x5B\x5D-\x7E]+$/', $scopeToken)) {
                throw new HttpException('invalid scope', 400);
            }
        }

        // state is OPTIONAL in remoteStorage
        $state = $request->getQueryParameter('state', false, null);
        if (!is_null($state)) {
            if (1 !== preg_match('/^[\x20-\x7E]+$/', $state)) {
                throw new HttpException('invalid state', 400);
            }
        }
    }

    private function validateClient(Request $request)
    {
        $clientId = $request->getQueryParameter('client_id');
        $redirectUri = $request->getQueryParameter('redirect_uri');

        // redirectUri has to start with clientId (or be equal)
        if (0 !== strpos($redirectUri, $clientId)) {
            throw new HttpException('"redirect_uri" does not match "client_id"', 400);
        }
    }
}
