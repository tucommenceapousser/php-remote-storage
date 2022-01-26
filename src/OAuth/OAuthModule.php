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
    private $expiresIn = 7776000;   // 90 days

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
    public function setExpiresIn($expiresIn): void
    {
        $this->expiresIn = (int) $expiresIn;
    }

    public function getAuthorize(Request $request, $userId)
    {
        $this->validateRequest($request);
        $clientOrigin = $this->validateClient($request);

        // ask for approving this client/scope
        return new HtmlResponse(
            $this->tpl->render(
                'authorize',
                [
                    'user_id' => $userId,
                    'client_origin' => $clientOrigin,
                    'scope' => $request->getQueryParameter('scope'),
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
            if (null !== $state) {
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
        if (null !== $state) {
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

    private function validateRequest(Request $request): void
    {
        // we enforce that all parameter are set, nothing is "OPTIONAL"
        $clientId = $request->getQueryParameter('client_id');
        if (1 !== preg_match('/^[\x20-\x7E]+$/', $clientId)) {
            throw new HttpException('invalid client_id', 400);
        }

        // XXX we also should enforce HTTPS
        $redirectUri = $request->getQueryParameter('redirect_uri');
        if (false === filter_var($redirectUri, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
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
        if (null !== $state) {
            if (1 !== preg_match('/^[\x20-\x7E]+$/', $state)) {
                throw new HttpException('invalid state', 400);
            }
        }
    }

    /**
     * @return string
     */
    private function validateClient(Request $request)
    {
        $clientId = $request->getQueryParameter('client_id');
        $redirectUri = $request->getQueryParameter('redirect_uri');

        if (false === $clientIdOrigin = self::determineOrigin($clientId)) {
            throw new HttpException('unable to determine Origin for "client_id"', 400);
        }
        if (false === $redirectUriOrigin = self::determineOrigin($redirectUri)) {
            throw new HttpException('unable to determine Origin for "redirect_uri"', 400);
        }
        if ($clientIdOrigin !== $redirectUriOrigin) {
            throw new HttpException('"client_id" and "redirect_uri" do not have the same Origin', 400);
        }

        return $clientIdOrigin;
    }

    /**
     * @param string $inputUrl
     *
     * @return false|string
     */
    private static function determineOrigin($inputUrl)
    {
        if (false === filter_var($inputUrl, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parsedInputUrl = parse_url($inputUrl);
        $originUrl = $parsedInputUrl['scheme'].'://'.$parsedInputUrl['host'];
        if (\array_key_exists('port', $parsedInputUrl)) {
            $originUrl .= ':'.(string) $parsedInputUrl['port'];
        }

        return $originUrl;
    }
}
