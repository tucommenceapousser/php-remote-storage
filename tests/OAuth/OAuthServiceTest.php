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

require_once __DIR__.'/Test/TestApproval.php';
require_once __DIR__.'/Test/TestAuthorizationCode.php';
require_once __DIR__.'/Test/TestAccessToken.php';
require_once __DIR__.'/Test/TestClient.php';
require_once __DIR__.'/Test/TestTemplateManager.php';

use fkooman\Http\Request;
use fkooman\RemoteStorage\OAuth\Test\TestAccessToken;
use fkooman\RemoteStorage\OAuth\Test\TestApproval;
use fkooman\RemoteStorage\OAuth\Test\TestAuthorizationCode;
use fkooman\RemoteStorage\OAuth\Test\TestClient;
use fkooman\RemoteStorage\OAuth\Test\TestTemplateManager;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Basic\BasicAuthentication;
use PHPUnit_Framework_TestCase;

class OAuthServiceTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\RemoteStorage\OAuth\OAuthServer */
    private $oauthService;

    public function setUp()
    {
        $resourceServer = $this->getMockBuilder('fkooman\RemoteStorage\OAuth\ResourceServerStorageInterface')->getMock();
        $resourceServer->expects($this->any())->method('getResourceServer')->will($this->returnValue(
            new ResourceServer(
                'r_id',
                'post',
                'SECRET'
            )
        ));

        $io = $this->getMockBuilder('fkooman\IO\IO')->getMock();
        $io->expects($this->any())->method('getTime')->will($this->returnValue(1234567890));

        $authenticationPlugin = new AuthenticationPlugin();
        $authenticationPlugin->register($this->getBasicAuth('user'), 'user');
        $authenticationPlugin->register($this->getBasicAuth('client'), 'client');
        $authenticationPlugin->register($this->getBasicAuth('resource_server'), 'resource_server');

        $this->oauthService = new OAuthService(
            new TestTemplateManager(),
            new TestClient(),
            $resourceServer,
            new TestApproval(),
            new TestAuthorizationCode(),
            new TestAccessToken(),
            [],
            $io
        );
        $this->oauthService->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    }

    public function testGetAuthorize()
    {
        $query = [
            'client_id' => 'test-client',
            'response_type' => 'code',
            'redirect_uri' => 'https://localhost/cb',
            'state' => '12345',
            'scope' => 'post',
        ];
        $request = $this->getAuthorizeRequest($query, 'GET');

        $this->assertSame(
            [
                'HTTP/1.1 200 OK',
                'Content-Type: text/html;charset=UTF-8',
                'X-Frame-Options: DENY',
                "Content-Security-Policy: default-src 'self'",
                'Content-Length: 275',
                '',
                '{"getAuthorize":{"user_id":"admin","client_id":"test-client","redirect_uri":"https:\/\/localhost\/cb","scope":"post","request_url":"https:\/\/oauth.example\/authorize?client_id=test-client&response_type=code&redirect_uri=https%3A%2F%2Flocalhost%2Fcb&state=12345&scope=post"}}',
            ],
            $this->oauthService->run($request)->toArray()
        );
    }

    public function testPostCodeAuthorize()
    {
        $query = [
            'client_id' => 'test-client',
            'redirect_uri' => 'https://localhost/cb',
            'state' => '12345',
            'response_type' => 'code',
            'scope' => 'post',
        ];
        $request = $this->getAuthorizeRequest($query, 'POST', ['approval' => 'yes']);

        $this->assertSame(
            [
                'HTTP/1.1 302 Found',
                'Content-Type: text/html;charset=UTF-8',
                'Location: https://localhost/cb?code=eyJjbGllbnRfaWQiOiJ0ZXN0LWNsaWVudCIsInVzZXJfaWQiOiJhZG1pbiIsImlzc3VlZF9hdCI6MTIzNDU2Nzg5MCwicmVkaXJlY3RfdXJpIjoiaHR0cHM6XC9cL2xvY2FsaG9zdFwvY2IiLCJzY29wZSI6InBvc3QifQ&state=12345',
                '',
                '',
            ],
            $this->oauthService->run($request)->toArray()
        );
    }

    public function testPostToken()
    {
        $request = new Request(
            [
                'HTTPS' => 'on',
                'SERVER_NAME' => 'oauth.example',
                'SERVER_PORT' => '443',
                'REQUEST_URI' => '/token',
                'SCRIPT_NAME' => '/index.php',
                'PATH_INFO' => '/token',
                'QUERY_STRING' => '',
                'HTTP_AUTHORIZATION' => 'Basic dGVzdC1jbGllbnQ6Zm9v',
                'REQUEST_METHOD' => 'POST',
            ],
            [
                'code' => 'eyJjbGllbnRfaWQiOiJ0ZXN0LWNsaWVudCIsInVzZXJfaWQiOiJhZG1pbiIsImlzc3VlZF9hdCI6MTIzNDU2Nzg5MCwicmVkaXJlY3RfdXJpIjoiaHR0cHM6XC9cL2xvY2FsaG9zdFwvY2IiLCJzY29wZSI6InBvc3QifQ',
                'scope' => 'post',
                'redirect_uri' => 'https://localhost/cb',
                'grant_type' => 'authorization_code',
                'client_id' => 'test-client',
            ]
        );

        $this->assertSame(
            [
                'HTTP/1.1 200 OK',
                'Content-Type: application/json',
                'Cache-Control: no-store',
                'Pragma: no-cache',
                'Content-Length: 167',
                '',
                '{"access_token":"eyJjbGllbnRfaWQiOiJ0ZXN0LWNsaWVudCIsInVzZXJfaWQiOiJhZG1pbiIsImlzc3VlZF9hdCI6MTIzNDU2Nzg5MCwic2NvcGUiOiJwb3N0In0","scope":"post","token_type":"bearer"}',
            ],
            $this->oauthService->run($request)->toArray()
        );
    }

    public function testPostIntrospect()
    {
        $request = new Request(
            [
                'HTTPS' => 'on',
                'SERVER_NAME' => 'oauth.example',
                'SERVER_PORT' => '443',
                'REQUEST_URI' => '/introspect',
                'SCRIPT_NAME' => '/index.php',
                'PATH_INFO' => '/introspect',
                'QUERY_STRING' => '',
                'HTTP_AUTHORIZATION' => 'Basic cmVzb3VyY2Vfc2VydmVyOmZvbw==',
                'REQUEST_METHOD' => 'POST',
            ],
            [
                'token' => 'eyJjbGllbnRfaWQiOiJ0ZXN0LWNsaWVudCIsInVzZXJfaWQiOiJhZG1pbiIsImlzc3VlZF9hdCI6MTIzNDU2Nzg5MCwic2NvcGUiOiJwb3N0In0',
            ]
        );

        $this->assertSame(
            [
                'HTTP/1.1 200 OK',
                'Content-Type: application/json',
                'Content-Length: 109',
                '',
                '{"active":true,"client_id":"test-client","scope":"post","token_type":"bearer","iat":1234567890,"sub":"admin"}',
            ],
            $this->oauthService->run($request)->toArray()
        );
    }

    private function getAuthorizeRequest(array $query, $requestMethod = 'GET', $postBody = [])
    {
        $q = http_build_query($query);

        return new Request(
            [
                'HTTPS' => 'on',
                'SERVER_NAME' => 'oauth.example',
                'SERVER_PORT' => '443',
                'REQUEST_URI' => sprintf('/authorize?%s', $q),
                'SCRIPT_NAME' => '/index.php',
                'PATH_INFO' => '/authorize',
                'QUERY_STRING' => $q,
                'HTTP_AUTHORIZATION' => 'Basic YWRtaW46Zm9v',
                'REQUEST_METHOD' => $requestMethod,
            ],
            $postBody
        );
    }

    private function getBasicAuth($userId)
    {
        return new BasicAuthentication(
            function ($userId) {
                return '$2y$10$CY3823B25ktbdu/fn5py4OH/reTiJiuk16uX7ZdqFhSwcXdzJ45cW'; // foo
            },
            [
                'realm' => 'OAuth AS',
            ]
        );
    }
}
