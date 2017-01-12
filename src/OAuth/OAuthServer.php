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
use fkooman\Http\Exception\UnauthorizedException;
use fkooman\Http\JsonResponse;
use fkooman\Http\RedirectResponse;
use fkooman\Http\Request;
use fkooman\IO\IO;
use fkooman\Rest\Plugin\Authentication\UserInfoInterface;

class OAuthServer
{
    /** @var ClientStorageInterface */
    private $clientStorage;

    /** @var ResourceServerStorageInterface */
    private $resourceServerStorage;

    /** @var ApprovalStorageInterface */
    private $approvalStorage;

    /** @var AuthorizationCodeStorageInterface */
    private $authorizationCodeStorage;

    /** @var AccessTokenStorageInterface */
    private $accessTokenStorage;

    /** @var array */
    private $options = [
        'require_state' => true,
    ];

    /** @var \fkooman\IO\IO */
    private $io;

    public function __construct(ClientStorageInterface $clientStorage, ResourceServerStorageInterface $resourceServerStorage, ApprovalStorageInterface $approvalStorage, AuthorizationCodeStorageInterface $authorizationCodeStorage, AccessTokenStorageInterface $accessTokenStorage, array $options = [], IO $io = null)
    {
        $this->clientStorage = $clientStorage;
        $this->resourceServerStorage = $resourceServerStorage;
        $this->approvalStorage = $approvalStorage;
        $this->authorizationCodeStorage = $authorizationCodeStorage;
        $this->accessTokenStorage = $accessTokenStorage;
        if (null === $io) {
            $io = new IO();
        }
        $this->options = array_merge($this->options, $options);
        $this->io = $io;
    }

    public function getAuthorize(Request $request, UserInfoInterface $userInfo)
    {
        $authorizeRequest = RequestValidation::validateAuthorizeRequest($request, $this->options['require_state']);

        $client = $this->clientStorage->getClient(
            $authorizeRequest['client_id'],
            $authorizeRequest['response_type'],
            $authorizeRequest['redirect_uri'],
            $authorizeRequest['scope']
        );
        if (false === $client) {
            throw new BadRequestException('client does not exist');
        }

        // verify authorize request with client information
        $this->validateAuthorizeRequestWithClient($client, $authorizeRequest);

        // if approval is already there, return redirect
        $approval = new Approval(
            $userInfo->getUserId(),
            $client->getClientId(),
            $authorizeRequest['response_type'],
            $authorizeRequest['scope']
        );

        if ($this->approvalStorage->isApproved($approval)) {
            // already approved
            return $this->handleApproval($client, $authorizeRequest, $userInfo);
        }

        // if not, show the approval dialog
        return [
            'user_id' => $userInfo->getUserId(),
            'client_id' => $client->getClientId(),
            'redirect_uri' => $authorizeRequest['redirect_uri'],
//            'response_type' => $authorizeRequest['response_type'],
            'scope' => $authorizeRequest['scope'],
            'request_url' => $request->getUrl()->toString(),
        ];
    }

    public function postAuthorize(Request $request, UserInfoInterface $userInfo)
    {
        // FIXME: referrer url MUST be exact request URL?
        $postAuthorizeRequest = RequestValidation::validatePostAuthorizeRequest($request, $this->options['require_state']);

        $client = $this->clientStorage->getClient(
            $postAuthorizeRequest['client_id'],
            $postAuthorizeRequest['response_type'],
            $postAuthorizeRequest['redirect_uri'],
            $postAuthorizeRequest['scope']
        );
        if (false === $client) {
            throw new BadRequestException('client does not exist');
        }

        // verify authorize request with client information
        $this->validateAuthorizeRequestWithClient($client, $postAuthorizeRequest);

        if ('yes' === $postAuthorizeRequest['approval']) {
            return $this->handleApproval($client, $postAuthorizeRequest, $userInfo);
        }

        return $this->handleDenial($postAuthorizeRequest, $userInfo);
    }

    public function postToken(Request $request, UserInfoInterface $clientUserInfo = null)
    {
        // FIXME: deal with not authenticated attempts! check if the client is
        // 'public/anonymous' or not, we have to deny hard here! check client_id
        // post parameter, check userInfo->getUserId to match it with client_id
        // etc.

        $tokenRequest = RequestValidation::validateTokenRequest($request);

        $client = $this->clientStorage->getClient(
            $tokenRequest['client_id']
        );
        if (null === $clientUserInfo) {
            // unauthenticated client
            if (null !== $client->getSecret()) {
                // if this is not null, authentication was actually required, but there was no attempt
                $e = new UnauthorizedException('not_authenticated', 'client authentication required for this client');
                $e->addScheme(
                    'Basic',
                    [
                        'realm' => 'OAuth AS',
                    ]
                );
                throw $e;
            }
        }

        if (null !== $clientUserInfo) {
            // if authenticated, client_id must match the authenticated user
            if ($clientUserInfo->getUserId() !== $tokenRequest['client_id']) {
                throw new BadRequestException('client_id does not match authenticated user');
            }
        }

        // check code was not used before
        if (!$this->authorizationCodeStorage->isFreshAuthorizationCode($tokenRequest['code'])) {
            throw new BadRequestException('authorization code can not be replayed');
        }
        $authorizationCode = $this->authorizationCodeStorage->retrieveAuthorizationCode($tokenRequest['code']);

        $issuedAt = $authorizationCode->getIssuedAt();
        if ($this->io->getTime() > $issuedAt + 600) {
            throw new BadRequestException('authorization code expired');
        }

        if ($authorizationCode->getClientId() !== $tokenRequest['client_id']) {
            throw new BadRequestException('client_id does not match expected value');
        }

        if (null !== $authorizationCode->getRedirectUri()) {
            if ($authorizationCode->getRedirectUri() !== $tokenRequest['redirect_uri']) {
                throw new BadRequestException('redirect_uri does not match expected value');
            }
        }

        // FIXME: grant_type must also match I think, but we do not have any
        // mapping logic from response_type to grant_type yet...

        // create an access token
        $accessToken = $this->accessTokenStorage->storeAccessToken(
            new AccessToken(
                $authorizationCode->getClientId(),
                $authorizationCode->getUserId(),
                $this->io->getTime(),
                $authorizationCode->getScope()
            )
        );

        $response = new JsonResponse();
        $response->setHeader('Cache-Control', 'no-store');
        $response->setHeader('Pragma', 'no-cache');
        $response->setBody(
            [
                'access_token' => $accessToken,
                'scope' => $authorizationCode->getScope(),
                'token_type' => 'bearer',
                //'expires_in' => 3600,
            ]
        );

        return $response;
    }

    public function postIntrospect(Request $request, UserInfoInterface $userInfo)
    {
        $introspectRequest = RequestValidation::validateIntrospectRequest($request);
        $accessToken = $this->accessTokenStorage->retrieveAccessToken($introspectRequest['token']);

        if (false === $accessToken) {
            $body = [
                'active' => false,
            ];
        } else {
            $resourceServerInfo = $this->resourceServerStorage->getResourceServer($userInfo->getUserId());
            $resourceServerScope = new Scope($resourceServerInfo->getScope());
            $accessTokenScope = new Scope($accessToken->getScope());
            // the scopes from the access token needs to be supported by the
            // resource server, otherwise the token is not valid (for this
            // resource server, i.e. audience mismatch)

            if (!$resourceServerScope->hasScope($accessTokenScope)) {
                $body = [
                    'active' => false,
                ];
            } else {
                $body = [
                    'active' => true,
                    'client_id' => $accessToken->getClientId(),
                    'scope' => $accessToken->getScope(),
                    'token_type' => 'bearer',
                    'iat' => $accessToken->getIssuedAt(),
                    'sub' => $accessToken->getUserId(),
                ];
            }
        }

        $response = new JsonResponse();
        $response->setBody($body);

        return $response;
    }

    private function validateAuthorizeRequestWithClient(Client $client, array $authorizeRequest)
    {
        if ($client->getResponseType() !== $authorizeRequest['response_type']) {
            throw new BadRequestException('response_type not supported by client');
        }

        if (null !== $authorizeRequest['redirect_uri']) {
            if ($client->getRedirectUri() !== $authorizeRequest['redirect_uri']) {
                throw new BadRequestException('redirect_uri not supported by client');
            }
        }

        if (null !== $authorizeRequest['scope']) {
            $requestScope = new Scope($authorizeRequest['scope']);
            $clientScope = new Scope($client->getScope());
            if (!$clientScope->hasScope($requestScope)) {
                throw new BadRequestException('scope not supported by client');
            }
        }
    }

    private function handleApproval(Client $client, array $postAuthorizeRequest, UserInfoInterface $userInfo)
    {
        // store the approval if not yet approved
        $approval = new Approval(
            $userInfo->getUserId(),
            $client->getClientId(),
            $postAuthorizeRequest['response_type'],
            $postAuthorizeRequest['scope']
        );

        if (!$this->approvalStorage->isApproved($approval)) {
            $this->approvalStorage->storeApproval($approval);
        }

        switch ($postAuthorizeRequest['response_type']) {
            case 'code':
                return $this->handleCodeApproval($client, $postAuthorizeRequest, $userInfo);
            case 'token':
                return $this->handleTokenApproval($client, $postAuthorizeRequest, $userInfo);
            default:
                throw new BadRequestException('invalid response_type');
        }
    }

    private function handleDenial(array $postAuthorizeRequest, UserInfoInterface $userInfo)
    {
        switch ($postAuthorizeRequest['response_type']) {
            case 'code':
                return $this->handleCodeDenial($postAuthorizeRequest, $userInfo);
            case 'token':
                return $this->handleTokenDenial($postAuthorizeRequest, $userInfo);
            default:
                throw new BadRequestException('invalid response_type');
        }
    }

    private function handleCodeApproval(Client $client, array $postAuthorizeRequest, UserInfoInterface $userInfo)
    {
        // generate authorization code and redirect back to client
        $code = $this->authorizationCodeStorage->storeAuthorizationCode(
            new AuthorizationCode(
                $client->getClientId(),
                $userInfo->getUserId(),
                $this->io->getTime(),
                $postAuthorizeRequest['redirect_uri'],
                $postAuthorizeRequest['scope']
            )
        );

        $separator = false === strpos($postAuthorizeRequest['redirect_uri'], '?') ? '?' : '&';

        $redirectTo = sprintf(
            '%s%scode=%s&state=%s',
            $postAuthorizeRequest['redirect_uri'],
            $separator,
            $code,
            $postAuthorizeRequest['state']
        );

        return new RedirectResponse(
            $redirectTo,
            302
        );
    }

    private function handleCodeDenial(array $postAuthorizeRequest, UserInfoInterface $userInfo)
    {
        $separator = false === strpos($postAuthorizeRequest['redirect_uri'], '?') ? '?' : '&';

        $redirectTo = sprintf(
            '%s%serror=access_denied&state=%s',
            $postAuthorizeRequest['redirect_uri'],
            $separator,
            $postAuthorizeRequest['state']
        );

        return new RedirectResponse(
            $redirectTo,
            302
        );
    }

    private function handleTokenApproval(Client $client, array $postAuthorizeRequest, UserInfoInterface $userInfo)
    {
        // generate access token and redirect back to client
        $accessToken = $this->accessTokenStorage->storeAccessToken(
            new AccessToken(
                $client->getClientId(),
                $userInfo->getUserId(),
                $this->io->getTime(),
                $postAuthorizeRequest['scope']
            )
        );

        // InputValidation already checks that the redirect_uri does not have
        // a fragment...
        $redirectTo = sprintf(
            '%s#access_token=%s&token_type=bearer&state=%s',
            $postAuthorizeRequest['redirect_uri'],
            $accessToken,
            $postAuthorizeRequest['state']
        );

        return new RedirectResponse(
            $redirectTo,
            302
        );
    }

    private function handleTokenDenial(array $postAuthorizeRequest, UserInfoInterface $userInfo)
    {
        // InputValidation already checks that the redirect_uri does not have
        // a fragment...
        $redirectTo = sprintf(
            '%s#error=access_denied&state=%s',
            $postAuthorizeRequest['redirect_uri'],
            $postAuthorizeRequest['state']
        );

        return new RedirectResponse(
            $redirectTo,
            302
        );
    }
}
