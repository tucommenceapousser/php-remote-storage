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

use PDO;
use fkooman\Http\Request;
use fkooman\Json\Json;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use PHPUnit_Framework_TestCase;

class RemoteStorageServiceTest extends PHPUnit_Framework_TestCase
{
    private $r;

    public function setUp()
    {
        // Create a stub for the SomeClass class.
        $ioStub = $this->getMockBuilder('fkooman\IO\IO')
                     ->getMock();

        // Configure the stub.
        $ioStub->method('getRandom')
             ->will($this->onConsecutiveCalls(2, 3, 5, 7));

        $md = new MetadataStorage(
            new PDO(
                $GLOBALS['DB_DSN'],
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD']
            ),
            '',
            $ioStub
        );
        $md->initDatabase();

        $tempFile = tempnam(sys_get_temp_dir(), '');
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
        mkdir($tempFile);
        $document = new DocumentStorage($tempFile);
        $remoteStorage = new RemoteStorage($md, $document);

        $stub = $this->getMockBuilder('fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication')
                     ->disableOriginalConstructor()
                     ->getMock();
        $stub->method('execute')->willReturn(
            new TokenInfo(
                array(
                    'active' => true,
                    'sub' => 'admin',
                    'scope' => 'foo:rw',
                )
            )
        );

        $this->r = new RemoteStorageService($remoteStorage);
        $this->r->getPluginRegistry()->registerDefaultPlugin($stub);
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
                $h,
                array(
                    'SERVER_NAME' => 'www.example.org',
                    'SERVER_PORT' => 80,
                    'QUERY_STRING' => '',
                    'REQUEST_URI' => '/index.php'.$urlPath,
                    'SCRIPT_NAME' => '/index.php',
                    'PATH_INFO' => $urlPath,
                    'REQUEST_METHOD' => 'GET',
                    'HTTP_ORIGIN' => 'https://foo.bar.example.org',
                )
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
                'Content-Type: application/json',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Etag: "1:2"',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                'Content-Length: 0',
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
                'Content-Type: application/json',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Etag: "1:2"',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                'Content-Length: 0',
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
                'Expires: 0',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Etag: "1:2"',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                'Content-Length: 12',
                '',
                'Hello World!',
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
                'Content-Length: 30',
                '',
                '{"error":"document not found"}',
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
                'Content-Type: application/json',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Etag: "1:2"',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                'Content-Length: 0',
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
                'Content-Type: application/json',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Etag: "1:2"',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                'Content-Length: 0',
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
                'Content-Length: 30',
                '',
                '{"error":"document not found"}',
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
                'Expires: 0',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Etag: "e:404"',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                'Content-Length: 77',
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
                'Content-Type: application/json',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Etag: "1:2"',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                'Content-Length: 0',
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
                'Expires: 0',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Etag: "1:7"',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                'Content-Length: 150',
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
                'Content-Type: application/json',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Etag: "1:2"',
                'Access-Control-Expose-Headers: ETag, Content-Length',
                'Content-Length: 0',
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
                'Expires: 0',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Etag: "1:2"',
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
                'Expires: 0',
                'Access-Control-Allow-Origin: https://foo.bar.example.org',
                'Etag: "1:7"',
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
                '',
                '{"error":"version mismatch"}',
            ),
            $response->toArray()
        );
    }
}
