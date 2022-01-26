<?php

namespace fkooman\RemoteStorage;

use DateTime;
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

    /** @var array */
    private $auth = [];

    public function __construct(string $appDir, string $requestRoot, Config $config, SessionInterface $session, RandomInterface $random, DateTime $dateTime)
    {
        $serverMode = $config->serverMode;
        $this->templateManager = new TwigTpl(
            [
                sprintf('%s/views', $appDir),
                sprintf('%s/config/views', $appDir),
            ],
            'development' !== $serverMode ? sprintf('%s/data/tpl', $appDir) : null
        );
        $this->templateManager->setDefault(
            [
                'requestRoot' => $requestRoot,
                'serverMode' => $serverMode,
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

        $this->apiModule = new ApiModule($remoteStorage, $config->serverMode);
        $this->uiModule = new UiModule($remoteStorage, $this->templateManager, $tokenStorage);
        $this->webfingerModule = new WebfingerModule($config->serverMode);
        $this->oauthModule = new OAuthModule($this->templateManager, $tokenStorage, $random, $dateTime);
        $this->auth['form'] = new FormAuthentication($session, $this->templateManager, $config->Users->asArray());
        $this->auth['bearer'] = new BearerAuthentication($tokenStorage);
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
                    $tokenInfo = $this->auth['bearer']->requireAuth($request);

                    return $this->apiModule->put($request, $tokenInfo);
                case 'DELETE':
                    $tokenInfo = $this->auth['bearer']->requireAuth($request);

                    return $this->apiModule->delete($request, $tokenInfo);
                case 'OPTIONS':
                    return $this->apiModule->options($request);
                case 'HEAD':
                    $tokenInfo = $this->auth['bearer']->optionalAuth($request);

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
                $userId = $this->auth['form']->requireAuth($request);
                if ($userId instanceof Response) {
                    return $userId;
                }

                return $this->oauthModule->getAuthorize($request, $userId);
            case '/':
                $userId = $this->auth['form']->requireAuth($request);
                if ($userId instanceof Response) {
                    return $userId;
                }

                return $this->uiModule->getHome($request, $userId);
            case '/logout':
                return $this->auth['form']->logout($request);
            default:
                $tokenInfo = $this->auth['bearer']->optionalAuth($request);

                return $this->apiModule->get($request, $tokenInfo);
        }
    }

    private function handlePost(Request $request): Response
    {
        switch ($request->getPathInfo()) {
            case '/':
                $userId = $this->auth['form']->requireAuth($request);

                return $this->uiModule->postHome($request, $userId);
            case '/authorize':
                $userId = $this->auth['form']->requireAuth($request);

                return $this->oauthModule->postAuthorize($request, $userId);
            case '/authenticate':
                return $this->auth['form']->verifyAuth($request);
            case '/logout':
                return $this->auth['form']->logout($request);
            default:
                throw new HttpException('not found', 404);
        }
    }
}
