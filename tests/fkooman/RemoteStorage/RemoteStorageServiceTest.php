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

require_once __DIR__.'/Test/TestTokenValidator.php';
require_once __DIR__.'/Test/TestTemplateManager.php';
require_once __DIR__.'/Test/TestApproval.php';
require_once __DIR__.'/Test/TestAuthorizationCode.php';
require_once __DIR__.'/Test/TestAccessToken.php';

use PDO;
use fkooman\Http\Request;
use fkooman\Json\Json;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Authentication\Basic\BasicAuthentication;
use PHPUnit_Framework_TestCase;
use fkooman\RemoteStorage\Test\TestApproval;
use fkooman\RemoteStorage\Test\TestTokenValidator;
use fkooman\RemoteStorage\Test\TestTemplateManager;
use fkooman\RemoteStorage\Test\TestAuthorizationCode;
use fkooman\RemoteStorage\Test\TestAccessToken;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;

class RemoteStorageServiceTest extends PHPUnit_Framework_TestCase
{
    private $r;

    private $tempFile;

    public function setUp()
    {
        $ioStub = $this->getMockBuilder('fkooman\IO\IO')
                     ->getMock();
        $ioStub->method('getRandom')
             ->will($this->onConsecutiveCalls(2, 3, 5, 7));

        $db = new PDO(
            $GLOBALS['DB_DSN'],
            $GLOBALS['DB_USER'],
            $GLOBALS['DB_PASSWD']
        );

        $md = new MetadataStorage($db, '', $ioStub);
        $md->initDatabase();

        $tempFile = tempnam(sys_get_temp_dir(), '');
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
        mkdir($tempFile);
        $this->tempFile = $tempFile;

        $document = new DocumentStorage($tempFile);
        $remoteStorage = new RemoteStorage($md, $document);

        $approvalManagementStorage = new ApprovalManagementStorage($db);

        $userAuth = new BasicAuthentication(
            function ($userId) {
                if ('foo' === $userId) {
                    return '$2y$10$DcG2jZ.V1XC7vMA0O1R5leI8advDzgcpkiHaPcP7/SsvHmNOGwRRK';
                }

                return false;
            },
            array('realm' => 'remoteStorage')
        );

        $apiAuth = new BearerAuthentication(new TestTokenValidator());

        $authenticationPlugin = new AuthenticationPlugin();
        $authenticationPlugin->register($userAuth, 'user');
        $authenticationPlugin->register($apiAuth, 'api');

        $this->r = new RemoteStorageService(
            $remoteStorage,
            $approvalManagementStorage,
            new TestTemplateManager(),
            new RemoteStorageClientStorage(),
            new RemoteStorageResourceServer(),
            new TestApproval(),
            new TestAuthorizationCode(),
            new TestAccessToken(),
            array('server_mode' => 'production'),
            $ioStub
        );
        $this->r->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    }

    private function getPutRequest($urlPath, array $h = array())
    {
        return new Request(
            array_merge(
                $h,
                array(
                    'SERVER_NAME' => 'www.example.org',
                    'SERVER_PORT' => 80,
                    'QUERY_STRING' => '',
                    'REQUEST_URI' => '/index.php'.$urlPath,
                    'SCRIPT_NAME' => '/index.php',
                    'PATH_INFO' => $urlPath,
                    'REQUEST_METHOD' => 'PUT',
                    'HTTP_AUTHORIZATION' => 'Bearer test_token',
                    'HTTP_ORIGIN' => 'https://foo.bar.example.org',
                    'CONTENT_TYPE' => 'text/plain',
                )
            ),
            null,
            'Hello World!'
        );
    }

    private function getGetRequest($urlPath, array $h = array())
    {
        return new Request(
            array_merge(
                array(
                    'SERVER_NAME' => 'www.example.org',
                    'SERVER_PORT' => 80,
                    'QUERY_STRING' => '',
                    'REQUEST_URI' => '/index.php'.$urlPath,
                    'SCRIPT_NAME' => '/index.php',
                    'HTTP_AUTHORIZATION' => 'Bearer test_token',
                    'PATH_INFO' => $urlPath,
                    'REQUEST_METHOD' => 'GET',
                    'HTTP_ORIGIN' => 'https://foo.bar.example.org',
                ),
                $h
            )
        );
    }

    private function getDeleteRequest($urlPath, array $h = array())
    {
        return new Request(
            array_merge(
                $h,
                array(
                    'SERVER_NAME' => 'www.example.org',
                    'SERVER_PORT' => 80,
                    'HTTP_AUTHORIZATION' => 'Bearer test_token',
                    'QUERY_STRING' => '',
                    'REQUEST_URI' => '/index.php'.$urlPath,
                    'PATH_INFO' => $urlPath,
                    'SCRIPT_NAME' => '/index.php',
                    'REQUEST_METHOD' => 'DELETE',
                    'HTTP_ORIGIN' => 'https://foo.bar.example.org',
                )
            )
        );
    }

    public function testStripQuotes()
    {
        $this->assertEquals(array('foo'), $this->r->stripQuotes('"foo"'));
        $this->assertEquals(array('foo', 'bar', 'baz'), $this->r->stripQuotes('"foo","bar","baz"'));
        $this->assertEquals(array('foo', 'bar', 'baz'), $this->r->stripQuotes('"foo", "bar",  "baz"'));
        $this->assertEquals(array('*'), $this->r->stripQuotes('*'));
    }

    public function testPutDocument()
    {
        $request = $this->getPutRequest('/admin/foo/bar/baz.txt');
        $response = $this->r->run($request);

        $this->assertSame(
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: text/html;charset=UTF-8',
                'Etag: "1:2"',
                'Content-Length: 0',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                null,
            ),
            $response->toArray()
        );
    }

    public function testGetDocument()
    {
        $request = $this->getPutRequest('/admin/foo/bar/baz.txt');
        $response = $this->r->run($request);
        $this->assertSame(
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: text/html;charset=UTF-8',
                'Etag: "1:2"',
                'Content-Length: 0',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                null,
            ),
            $response->toArray()
        );

        $request = $this->getGetRequest('/admin/foo/bar/baz.txt');
        $response = $this->r->run($request);

        $this->assertSame(
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: text/plain',
                'Etag: "1:2"',
                'Accept-Ranges: bytes',
                sprintf('X-Sendfile: %s/admin/foo/bar/baz.txt', $this->tempFile),
                'Expires: 0',
                'Cache-Control: no-cache',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                '',
            ),
            $response->toArray()
        );
    }

    public function testGetNonExistingDocument()
    {
        $request = $this->getGetRequest('/admin/foo/bar/baz.txt');
        $response = $this->r->run($request);
        $this->assertSame(
            array(
                'HTTP/1.1 404 Not Found',
                'Content-Type: application/json',
                'Content-Length: 61',
                'Expires: 0',
                'Cache-Control: no-cache',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                '{"error":"document \"\/admin\/foo\/bar\/baz.txt\" not found"}',
            ),
            $response->toArray()
        );
    }

    public function testDeleteDocument()
    {
        $request = $this->getPutRequest('/admin/foo/bar/baz.txt');
        $response = $this->r->run($request);
        $this->assertSame(
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: text/html;charset=UTF-8',
                'Etag: "1:2"',
                'Content-Length: 0',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                null,
            ),
            $response->toArray()
        );

        $request = $this->getDeleteRequest('/admin/foo/bar/baz.txt');
        $response = $this->r->run($request);
        $this->assertSame(
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: text/html;charset=UTF-8',
                'Etag: "1:2"',
                'Content-Length: 0',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                null,
            ),
            $response->toArray()
        );
    }

    public function testDeleteNonExistingDocument()
    {
        $request = $this->getDeleteRequest('/admin/foo/bar/baz.txt');
        $response = $this->r->run($request);
        $this->assertSame(
            array(
                'HTTP/1.1 404 Not Found',
                'Content-Type: application/json',
                'Content-Length: 61',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                '{"error":"document \"\/admin\/foo\/bar\/baz.txt\" not found"}',
            ),
            $response->toArray()
        );
    }

    public function testGetNonExistingFolder()
    {
        $request = $this->getGetRequest('/admin/foo/bar/');
        $response = $this->r->run($request);
        $this->assertSame(
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: application/ld+json',
                'Etag: "e:404"',
                'Content-Length: 77',
                'Expires: 0',
                'Cache-Control: no-cache',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                '{"@context":"http:\/\/remotestorage.io\/spec\/folder-description","items":{}}',
            ),
            $response->toArray()
        );
    }

    public function testGetFolder()
    {
        $request = $this->getPutRequest('/admin/foo/bar/baz.txt');
        $response = $this->r->run($request);
        $this->assertSame(
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: text/html;charset=UTF-8',
                'Etag: "1:2"',
                'Content-Length: 0',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                null,
            ),
            $response->toArray()
        );

        $request = $this->getGetRequest('/admin/foo/bar/');
        $response = $this->r->run($request);
        $this->assertSame(
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: application/ld+json',
                'Etag: "1:7"',
                'Content-Length: 150',
                'Expires: 0',
                'Cache-Control: no-cache',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                '{"@context":"http:\/\/remotestorage.io\/spec\/folder-description","items":{"baz.txt":{"Content-Length":12,"ETag":"1:2","Content-Type":"text\/plain"}}}',
            ),
            $response->toArray()
        );
    }

    public function testGetSameVersionDocument()
    {
        $request = $this->getPutRequest('/admin/foo/bar/baz.txt');
        $response = $this->r->run($request);
        $this->assertSame(
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: text/html;charset=UTF-8',
                'Etag: "1:2"',
                'Content-Length: 0',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                null,
            ),
            $response->toArray()
        );

        $request = $this->getGetRequest(
            '/admin/foo/bar/baz.txt',
            array(
                'If-None-Match' => '"1:2"',
            )
        );
        $response = $this->r->run($request);
        $this->assertSame(
            array(
                'HTTP/1.1 304 Not Modified',
                'Content-Type: text/plain',
                'Etag: "1:2"',
                'Expires: 0',
                'Cache-Control: no-cache',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                '',
            ),
            $response->toArray()
        );
    }

    public function testGetSameVersionFolder()
    {
        $request = $this->getPutRequest('/admin/foo/bar/baz.txt');
        $response = $this->r->run($request);
        $request = $this->getGetRequest(
            '/admin/foo/bar/',
            array(
                'If-None-Match' => '"1:7"',
            )
        );
        $response = $this->r->run($request);
        $this->assertSame(
            array(
                'HTTP/1.1 304 Not Modified',
                'Content-Type: application/ld+json',
                'Etag: "1:7"',
                'Expires: 0',
                'Cache-Control: no-cache',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                '',
            ),
            $response->toArray()
        );
    }

    public function testPutNonMatchingVersion()
    {
        $request = $this->getPutRequest('/admin/foo/bar/baz.txt');
        $response = $this->r->run($request);

        $request = $this->getPutRequest(
            '/admin/foo/bar/baz.txt',
            array(
                'If-Match' => '"non-matching-version"',
            )
        );
        $response = $this->r->run($request);
        $this->assertSame(
            array(
                'HTTP/1.1 412 Precondition Failed',
                'Content-Type: application/json',
                'Content-Length: 28',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                '{"error":"version mismatch"}',
            ),
            $response->toArray()
        );
    }

    public function testDeleteNonMatchingVersion()
    {
        $request = $this->getPutRequest('/admin/foo/bar/baz.txt');
        $response = $this->r->run($request);

        $request = $this->getDeleteRequest(
            '/admin/foo/bar/baz.txt',
            array(
                'If-Match' => '"non-matching-version"',
            )
        );
        $response = $this->r->run($request);
        $this->assertSame(
            array(
                'HTTP/1.1 412 Precondition Failed',
                'Content-Type: application/json',
                'Content-Length: 28',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                '{"error":"version mismatch"}',
            ),
            $response->toArray()
        );
    }

    public function testWebFinger()
    {
        $q = 'resource=acct:foo@www.example.org';

        $request = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => $q,
                'REQUEST_URI' => sprintf('/.well-known/webfinger?%s', $q),
                'SCRIPT_NAME' => '/index.php',
                'PATH_INFO' => '/.well-known/webfinger',
                'REQUEST_METHOD' => 'GET',
            )
        );
        $response = $this->r->run($request);
        $this->assertSame(
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: application/jrd+json',
                'Content-Length: 866',
                'Expires: 0',
                'Cache-Control: no-cache',
                'Access-Control-Allow-Origin: "*"',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                '',
                '{"links":[{"href":"http:\/\/www.example.org\/foo","properties":{"http:\/\/remotestorage.io\/spec\/version":"draft-dejong-remotestorage-05","http:\/\/remotestorage.io\/spec\/web-authoring":null,"http:\/\/tools.ietf.org\/html\/rfc6749#section-4.2":"http:\/\/www.example.org\/_oauth\/authorize?login_hint=foo","http:\/\/tools.ietf.org\/html\/rfc6750#section-2.3":null,"http:\/\/tools.ietf.org\/html\/rfc7233":"GET"},"rel":"http:\/\/tools.ietf.org\/id\/draft-dejong-remotestorage"},{"href":"http:\/\/www.example.org\/foo","properties":{"http:\/\/remotestorage.io\/spec\/version":"draft-dejong-remotestorage-03","http:\/\/tools.ietf.org\/html\/rfc2616#section-14.16":"GET","http:\/\/tools.ietf.org\/html\/rfc6749#section-4.2":"http:\/\/www.example.org\/_oauth\/authorize?login_hint=foo","http:\/\/tools.ietf.org\/html\/rfc6750#section-2.3":false},"rel":"remotestorage"}]}',
            ),
            $response->toArray()
        );
    }
}
