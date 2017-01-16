<?php

namespace fkooman\RemoteStorage\Http;

use fkooman\RemoteStorage\ApiModule;
use fkooman\RemoteStorage\Http\Exception\HttpException;
use fkooman\RemoteStorage\OAuth\BearerAuthentication;
use fkooman\RemoteStorage\OAuth\OAuthModule;
use fkooman\RemoteStorage\OAuth\TokenStorage;
use fkooman\RemoteStorage\RandomInterface;
use fkooman\RemoteStorage\RemoteStorage;
use fkooman\RemoteStorage\TplInterface;
use fkooman\RemoteStorage\UiModule;
use fkooman\RemoteStorage\WebfingerModule;

class Controller
{
    private $formAuth;
    private $bearerAuth;
    private $oauthModule;
    private $apiModule;
    private $uiModule;
    private $webfingerModule;

    public function __construct(TplInterface $tpl, SessionInterface $session, TokenStorage $tokenStorage, RandomInterface $random, RemoteStorage $remoteStorage, array $userPass)
    {
        $serverMode = 'development';
        $this->formAuth = new FormAuthentication($session, $tpl, $userPass);
        $this->bearerAuth = new BearerAuthentication($tokenStorage);
        $this->oauthModule = new OAuthModule($tpl, $random, $tokenStorage);
        $this->apiModule = new ApiModule($remoteStorage, $serverMode);
        $this->uiModule = new UiModule($remoteStorage, $tpl, $tokenStorage);
        $this->webfingerModule = new WebfingerModule($serverMode);
    }

    public function run(Request $request)
    {
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
                $tokenInfo = $this->bearerAuth->requireAuth($request);

                return $this->apiModule->head($request, $tokenInfo);
            default:
                throw new HttpException('', 405);
       }
    }

    private function handleGet(Request $request)
    {
        switch ($request->getPathInfo()) {
            case '/.well-known/webfinger':

                return $this->webfingerModule->getWebfinger($request);
            case '/_oauth/authorize':
                $userId = $this->formAuth->requireAuth($request);
                if ($userId instanceof Response) {
                    return $userId;
                }

                return $this->oauthModule->getAuthorize($request, $userId);
            case '/':
                $userId = $this->formAuth->optionalAuth($request);

                return $this->uiModule->getRootPage($request, $userId);
            case '/account':
                $userId = $this->formAuth->requireAuth($request);
                if ($userId instanceof Response) {
                    return $userId;
                }

                return $this->uiModule->getAccountPage($request, $userId);
            default:
                $tokenInfo = $this->bearerAuth->optionalAuth($request);

                return $this->apiModule->get($request, $tokenInfo);
        }
    }

    private function handlePost(Request $request)
    {
        switch ($request->getPathInfo()) {
            case '/account':
                $userId = $this->formAuth->requireAuth($request);

                return $this->uiModule->postAccountPage($request, $userId);

            case '/_oauth/authorize':
                $userId = $this->formAuth->requireAuth($request);

                return $this->oauthModule->postAuthorize($request, $userId);
            case '/_auth/form/verify':

                return $this->formAuth->verifyAuth($request);
            default:
                throw new HttpException('', 404);
        }
    }
}

//$request = new Request($_SERVER, $_GET, $_POST, file_get_contents('php://input'));
