<?php

declare(strict_types=1);

/*
 * php-remote-storage - PHP remoteStorage implementation
 *
 * Copyright: 2016 SURFnet
 * Copyright: 2022 François Kooman <fkooman@tuxed.net>
 *
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace fkooman\RemoteStorage\OAuth;

use fkooman\RemoteStorage\Http\Exception\HttpException;
use fkooman\RemoteStorage\Http\Request;

class BearerAuthentication
{
    /** @var TokenStorage */
    private $tokenStorage;

    /** @var string */
    private $realm;

    public function __construct(TokenStorage $tokenStorage, $realm = 'Protected Area')
    {
        $this->tokenStorage = $tokenStorage;
        $this->realm = $realm;
    }

    public function optionalAuth(Request $request)
    {
        $authorizationHeader = $request->getHeader('HTTP_AUTHORIZATION', false, null);

        // is authorization header there?
        if (null === $authorizationHeader || empty($authorizationHeader)) {
            return false;
        }

        return $this->requireAuth($request);
    }

    public function requireAuth(Request $request)
    {
        $authorizationHeader = $request->getHeader('HTTP_AUTHORIZATION');

        // validate the HTTP_AUTHORIZATION header
        if (false === $bearerToken = self::getBearerToken($authorizationHeader)) {
            throw $this->invalidTokenException();
        }

        $accessTokenKey = $bearerToken[0];
        $accessToken = $bearerToken[1];

        $tokenInfo = $this->tokenStorage->get($accessTokenKey);
        if (false === $tokenInfo) {
            throw $this->invalidTokenException();
        }

        // time safe string compare, using polyfill on PHP < 5.6
        if (hash_equals($tokenInfo['access_token'], $accessToken)) {
            return new TokenInfo($tokenInfo);
        }

        throw $this->invalidTokenException();
    }

    private static function getBearerToken($authorizationHeader)
    {
        if (1 !== preg_match('|^Bearer ([a-zA-Z0-9-._~+/]+=*)$|', $authorizationHeader, $m)) {
            return false;
        }

        $bearerToken = $m[1];
        if (false === strpos($bearerToken, '.')) {
            return false;
        }

        return explode('.', $bearerToken);
    }

    private function invalidTokenException()
    {
        return new HttpException(
            'invalid_token',
            401,
            [
                'WWW-Authenticate' => sprintf(
                    'Bearer realm="%s",error="invalid_token"',
                    $this->realm
                ),
            ]
        );
    }
}
