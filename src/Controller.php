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

namespace fkooman\RemoteStorage;

use fkooman\RemoteStorage\Http\Exception\HttpException;
use fkooman\RemoteStorage\Http\FormAuthentication;
use fkooman\RemoteStorage\Http\Request;
use fkooman\RemoteStorage\Http\Response;
use fkooman\RemoteStorage\Http\SessionInterface;
use fkooman\RemoteStorage\OAuth\BearerAuthentication;
use fkooman\RemoteStorage\OAuth\OAuthModule;
use fkooman\RemoteStorage\OAuth\TokenStorage;
use PDO;

class Controller
{
    /** @var TwigTpl */
    private $templateManager;

    /** @var ApiModule */
    private $apiModule;

    /** @var UiModule */
    private $uiModule;

    /** @var WebfingerModule */
    private $webfingerModule;

    /** @var OAuthModule */
    private $oauthModule;

    private FormAuthentication $formAuth;
    private BearerAuthentication $bearerAuth;

    public function __construct(string $appDir, string $requestRoot, Config $config, SessionInterface $session)
    {
        $this->templateManager = new TwigTpl(
            [
                sprintf('%s/views', $appDir),
                sprintf('%s/config/views', $appDir),
            ],
            $config->productionMode() ? sprintf('%s/data/tpl', $appDir) : null
        );
        $this->templateManager->setDefault(
            [
                'requestRoot' => $requestRoot,
            ]
        );

        $db = new PDO(sprintf('sqlite:%s/data/rs.sqlite', $appDir));
        $metaDataStorage = new MetadataStorage($db);
        $metaDataStorage->init();

        $tokenStorage = new TokenStorage($db);
        $tokenStorage->init();

        $remoteStorage = new RemoteStorage(
            $metaDataStorage,
            new DocumentStorage(sprintf('%s/data/storage', $appDir))
        );

        $this->apiModule = new ApiModule($remoteStorage, $config->productionMode());
        $this->uiModule = new UiModule($remoteStorage, $this->templateManager, $tokenStorage);
        $this->webfingerModule = new WebfingerModule($config->productionMode());
        $this->oauthModule = new OAuthModule($this->templateManager, $tokenStorage);
        $this->formAuth = new FormAuthentication($session, $this->templateManager, $config->userList());
        $this->bearerAuth = new BearerAuthentication($tokenStorage);
    }

    public function run(Request $request): Response
    {
        try {
            switch ($request->getRequestMethod()) {
                case 'GET':
                    return $this->handleGet($request);

                case 'POST':
                    return $this->handlePost($request);

                case 'PUT':
                    $tokenInfo = $this->bearerAuth->requireAuth($request);

                    return $this->apiModule->put($request, $tokenInfo);

                case 'DELETE':
                    $tokenInfo = $this->bearerAuth->requireAuth($request);

                    return $this->apiModule->delete($request, $tokenInfo);

                case 'OPTIONS':
                    return $this->apiModule->options($request);

                case 'HEAD':
                    $tokenInfo = $this->bearerAuth->optionalAuth($request);

                    return $this->apiModule->head($request, $tokenInfo);

                default:
                    throw new HttpException('method not allowed', 405);
           }
        } catch (HttpException $e) {
            if ($request->isBrowser()) {
                $response = new Response($e->getCode(), 'text/html');
                $response->setBody(
                    $this->templateManager->render(
                        'error',
                        [
                            'code' => $e->getCode(),
                            'message' => $e->getMessage(),
                        ]
                    )
                );
            } else {
                // not a browser
                $response = new Response($e->getCode(), 'application/json');
                $response->addHeader('Access-Control-Allow-Origin', '*');
                $response->setBody(json_encode(['error' => $e->getMessage()]));
            }

            foreach ($e->getResponseHeaders() as $key => $value) {
                $response->addHeader($key, $value);
            }

            return $response;
        }
    }

    private function handleGet(Request $request): Response
    {
        switch ($request->getPathInfo()) {
            case '/.well-known/webfinger':
                return $this->webfingerModule->getWebfinger($request);

            case '/authorize':
                if (null === $userId = $this->formAuth->userId()) {
                    return $this->formAuth->requireAuth($request);
                }

                return $this->oauthModule->getAuthorize($request, $userId);

            case '/':
                if (null === $userId = $this->formAuth->userId()) {
                    return $this->formAuth->requireAuth($request);
                }

                return $this->uiModule->getHome($request, $userId);

            case '/logout':
                return $this->formAuth->logout($request);

            default:
                $tokenInfo = $this->bearerAuth->optionalAuth($request);

                return $this->apiModule->get($request, $tokenInfo);
        }
    }

    private function handlePost(Request $request): Response
    {
        switch ($request->getPathInfo()) {
            case '/':
                if (null === $userId = $this->formAuth->userId()) {
                    return $this->formAuth->requireAuth($request);
                }

                return $this->uiModule->postHome($request, $userId);

            case '/authorize':
                if (null === $userId = $this->formAuth->userId()) {
                    return $this->formAuth->requireAuth($request);
                }

                return $this->oauthModule->postAuthorize($request, $userId);

            case '/authenticate':
                return $this->formAuth->verifyAuth($request);

            case '/logout':
                return $this->formAuth->logout($request);

            default:
                throw new HttpException('not found', 404);
        }
    }
}
