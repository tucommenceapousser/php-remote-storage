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

namespace fkooman\RemoteStorage\Tests;

use DateTime;
use fkooman\RemoteStorage\Config;
use fkooman\RemoteStorage\Controller;
use fkooman\RemoteStorage\DocumentStorage;
use fkooman\RemoteStorage\Http\Request;
use fkooman\RemoteStorage\MetadataStorage;
use fkooman\RemoteStorage\OAuth\TokenStorage;
use fkooman\RemoteStorage\Path;
use fkooman\RemoteStorage\RemoteStorage;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class ControllerTest extends TestCase
{
    /** @var Controller */
    private $controller;

    protected function setUp(): void
    {
        // set up the directory structure
        $projectDir = \dirname(__DIR__);
        $tmpDir = sprintf('%s/%s', sys_get_temp_dir(), bin2hex(random_bytes(16)));
        mkdir($tmpDir);
        mkdir(sprintf('%s/config', $tmpDir));
        copy(
            sprintf('%s/config/server.dev.yaml.example', $projectDir),
            sprintf('%s/config/server.yaml', $tmpDir)
        );

        // copy the templates
        mkdir(sprintf('%s/views', $tmpDir));
        foreach (glob(sprintf('%s/views/*', $projectDir)) as $templateFile) {
            copy(
                $templateFile,
                sprintf('%s/views/%s', $tmpDir, basename($templateFile))
            );
        }

        mkdir(sprintf('%s/data', $tmpDir));
        $db = new PDO(sprintf('sqlite:%s/data/rs.sqlite', $tmpDir));
        $metadataStorage = new MetadataStorage($db);
        $metadataStorage->init();

        $remoteStorage = new RemoteStorage(
            new MetadataStorage($db),
            new DocumentStorage(sprintf('%s/data/storage', $tmpDir))
        );

        // add an access_token
        $tokenStorage = new TokenStorage($db);
        $tokenStorage->init();
        $tokenStorage->store('foo', 'abcd', 'efgh', 'https://example.org/', 'bar:r', new DateTime('2016-01-01 01:00:00'));
        $tokenStorage->store('foo', 'efgh', 'ihjk', 'https://example.org/', 'bar:rw', new DateTime('2016-01-01 01:00:00'));

        // add some files
        $remoteStorage->putDocument(new Path('/foo/public/hello.txt'), 'text/plain', 'Hello World!');
        $remoteStorage->putDocument(new Path('/foo/bar/hello.txt'), 'text/plain', 'Hello World!');

        $random = $this->getMockBuilder('\fkooman\RemoteStorage\RandomInterface')->getMock();
        $random->method('get')->will(static::onConsecutiveCalls('random_1', 'random_2'));

        $config = Config::fromFile(sprintf('%s/config/server.yaml', $tmpDir));
        $this->controller = new Controller($tmpDir, '/', $config, new TestSession(), $random, new DateTime('2016-01-01'));
    }

    public function testGetPublicFile(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'example.org',
                'SERVER_PORT' => 80,
                'REQUEST_URI' => '/foo/public/hello.txt',
                'SCRIPT_NAME' => '/index.php',
                'REQUEST_METHOD' => 'GET',
            ],
            [],
            [],
            ''
        );
        $response = $this->controller->run($request);
        static::assertSame(200, $response->getStatusCode());
        static::assertSame('Hello World!', $response->getBody());
    }

    public function testGetFile(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'example.org',
                'SERVER_PORT' => 80,
                'REQUEST_URI' => '/foo/bar/hello.txt',
                'SCRIPT_NAME' => '/index.php',
                'REQUEST_METHOD' => 'GET',
                'HTTP_AUTHORIZATION' => 'Bearer abcd.efgh',
            ],
            [],
            [],
            ''
        );
        $response = $this->controller->run($request);
        static::assertSame(200, $response->getStatusCode());
        static::assertSame('Hello World!', $response->getBody());
    }

    public function testGetFileNoCredential(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'example.org',
                'SERVER_PORT' => 80,
                'REQUEST_URI' => '/foo/bar/hello.txt',
                'SCRIPT_NAME' => '/index.php',
                'REQUEST_METHOD' => 'GET',
            ],
            [],
            [],
            ''
        );
        $response = $this->controller->run($request);
        static::assertSame(401, $response->getStatusCode());
        static::assertSame('{"error":"no_token"}', $response->getBody());
    }

    public function testPutFile(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'example.org',
                'SERVER_PORT' => 80,
                'REQUEST_URI' => '/foo/bar/test.txt',
                'SCRIPT_NAME' => '/index.php',
                'REQUEST_METHOD' => 'PUT',
                'HTTP_AUTHORIZATION' => 'Bearer efgh.ihjk',
                'CONTENT_TYPE' => 'text/plain',
            ],
            [],
            [],
            'Test'
        );
        $response = $this->controller->run($request);
        static::assertSame(200, $response->getStatusCode());
        static::assertSame('', $response->getBody());
    }

    public function testDeleteFile(): void
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'example.org',
                'SERVER_PORT' => 80,
                'REQUEST_URI' => '/foo/bar/hello.txt',
                'SCRIPT_NAME' => '/index.php',
                'REQUEST_METHOD' => 'DELETE',
                'HTTP_AUTHORIZATION' => 'Bearer efgh.ihjk',
                'CONTENT_TYPE' => 'text/plain',
            ],
            [],
            [],
            ''
        );
        $response = $this->controller->run($request);
        static::assertSame(200, $response->getStatusCode());
        static::assertSame('', $response->getBody());
    }

    public function testGetAuthorizationNotLoggedIn(): void
    {
        $queryParameters = [
            'client_id' => 'https://app.example.org',
            'redirect_uri' => 'https://app.example.org/callback.html',
            'response_type' => 'token',
            'scope' => 'foo:r',
        ];

        $request = new Request(
            [
                'SERVER_NAME' => 'example.org',
                'SERVER_PORT' => 80,
                'REQUEST_URI' => sprintf('/authorize?%s', http_build_query($queryParameters)),
                'SCRIPT_NAME' => '/index.php',
                'REQUEST_METHOD' => 'GET',
            ],
            $queryParameters,
            [],
            ''
        );
        $response = $this->controller->run($request);
        static::assertSame(200, $response->getStatusCode());
        static::assertSame(
            file_get_contents(
                sprintf('%s/data/testGetAuthorizationNotLoggedIn', __DIR__)
            ),
            $response->getBody()
        );
    }

//    public function testPostAuthorizationNotLoggedIn(): void
//    {
//    }

//    public function testGetAuthorizationLoggedIn(): void
//    {
//    }

//    public function testPostAuthorization(): void
//    {
//    }

    public function testGetWebfinger(): void
    {
        $queryParameters = [
            'resource' => 'acct:foo@example.org',
        ];

        $request = new Request(
            [
                'SERVER_NAME' => 'example.org',
                'SERVER_PORT' => 80,
                'REQUEST_URI' => sprintf('/.well-known/webfinger?%s', http_build_query($queryParameters)),
                'SCRIPT_NAME' => '/index.php',
                'REQUEST_METHOD' => 'GET',
                'HTTP_AUTHORIZATION' => 'Bearer efgh.ihjk',
                'CONTENT_TYPE' => 'text/plain',
            ],
            $queryParameters,
            [],
            ''
        );
        $response = $this->controller->run($request);
        static::assertSame(200, $response->getStatusCode());
        static::assertSame('{"links":[{"href":"http:\/\/example.org\/foo","properties":{"http:\/\/remotestorage.io\/spec\/version":"draft-dejong-remotestorage-05","http:\/\/remotestorage.io\/spec\/web-authoring":null,"http:\/\/tools.ietf.org\/html\/rfc6749#section-4.2":"http:\/\/example.org\/authorize?login_hint=foo","http:\/\/tools.ietf.org\/html\/rfc6750#section-2.3":"true","http:\/\/tools.ietf.org\/html\/rfc7233":null},"rel":"http:\/\/tools.ietf.org\/id\/draft-dejong-remotestorage"},{"href":"http:\/\/example.org\/foo","properties":{"http:\/\/remotestorage.io\/spec\/version":"draft-dejong-remotestorage-03","http:\/\/tools.ietf.org\/html\/rfc2616#section-14.16":false,"http:\/\/tools.ietf.org\/html\/rfc6749#section-4.2":"http:\/\/example.org\/authorize?login_hint=foo","http:\/\/tools.ietf.org\/html\/rfc6750#section-2.3":true},"rel":"remotestorage"}]}', $response->getBody());
    }
}
