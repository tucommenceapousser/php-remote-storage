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
use fkooman\Rest\Plugin\Bearer\TokenIntrospection;
use PHPUnit_Framework_TestCase;

class RemoteStorageServiceTest extends PHPUnit_Framework_TestCase
{
    private $r;

    public function setUp()
    {
        $md = new MetadataStorage(
            new PDO(
                $GLOBALS['DB_DSN'],
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD']
            )
        );
        $md->initDatabase();

        $tempFile = tempnam(sys_get_temp_dir(), '');
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
        mkdir($tempFile);
        $document = new DocumentStorage($tempFile);
        $remoteStorage = new RemoteStorage($md, $document);

        $stub = $this->getMockBuilder('fkooman\Rest\Plugin\Bearer\BearerAuthentication')
                     //->setMockClassName('fkooman\Rest\Plugin\Bearer\BearerAuthentication')
                     ->disableOriginalConstructor()
                     ->getMock();
        $stub->method('execute')->willReturn(
            new TokenIntrospection(
                array(
                    'active' => true,
                    'sub' => 'admin',
                    'scope' => 'foo:rw',
                )
            )
        );

        $this->r = new RemoteStorageService($remoteStorage);
        $this->r->registerBeforeEachMatchPlugin($stub);
    }

    /**
     * Method to create a new request object to set some default headers.
     */
    private function newRequest($requestMethod)
    {
        $request = new Request('https://www.example.org', $requestMethod);
        $request->setHeader('Origin', 'https://foo.bar.example.org');

        return $request;
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
        $request = $this->newRequest('PUT');
        $request->setPathInfo('/admin/foo/bar/baz.txt');
        $request->setContentType('text/plain');
        $request->setContent('Hello World!');

        $response = $this->r->run($request);

        $this->assertEquals('application/json', $response->getContentType());
        $this->assertEquals('https://foo.bar.example.org', $response->getHeader('Access-Control-Allow-Origin'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('/1:[a-z0-9]+/i', $response->getHeader('ETag'));
    }

    public function testGetDocument()
    {
        $request = $this->newRequest('PUT');
        $request->setPathInfo('/admin/foo/bar/baz.txt');
        $request->setContentType('text/plain');
        $request->setContent('Hello World!');
        $response = $this->r->run($request);
        $this->assertEquals(200, $response->getStatusCode());

        $request = $this->newRequest('GET');
        $request->setPathInfo('/admin/foo/bar/baz.txt');
        $response = $this->r->run($request);
        $this->assertEquals('text/plain', $response->getContentType());
        $this->assertEquals('Hello World!', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('/1:[a-z0-9]+/i', $response->getHeader('ETag'));
    }

    /**
     * @expectedException fkooman\Http\Exception\NotFoundException
     * @expectedExceptionMessage document not found
     */
    public function testGetNonExistingDocument()
    {
        $request = $this->newRequest('GET');
        $request->setPathInfo('/admin/foo/bar/baz.txt');
        $this->r->run($request);
    }

    public function testDeleteDocument()
    {
        $request = $this->newRequest('PUT');
        $request->setPathInfo('/admin/foo/bar/baz.txt');
        $request->setContentType('text/plain');
        $request->setContent('Hello World!');
        $response = $this->r->run($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('https://foo.bar.example.org', $response->getHeader('Access-Control-Allow-Origin'));

        $request = $this->newRequest('DELETE');
        $request->setPathInfo('/admin/foo/bar/baz.txt');
        $response = $this->r->run($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('https://foo.bar.example.org', $response->getHeader('Access-Control-Allow-Origin'));
        $this->assertRegexp('/1:[a-z0-9]+/i', $response->getHeader('ETag'));
    }

    /**
     * @expectedException fkooman\Http\Exception\NotFoundException
     * @expectedExceptionMessage document not found
     */
    public function testDeleteNonExistingDocument()
    {
        $request = $this->newRequest('DELETE');
        $request->setPathInfo('/admin/foo/bar/baz.txt');
        $this->r->run($request);
    }

    public function testGetNonExistingFolder()
    {
        $request = $this->newRequest('GET');
        $request->setPathInfo('/admin/foo/bar/');
        $response = $this->r->run($request);
        $this->assertEquals('application/ld+json', $response->getContentType());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('/e:[a-z0-9]+/i', $response->getHeader('ETag'));
        $this->assertEquals(
            array(
                '@context' => 'http://remotestorage.io/spec/folder-description',
                'items' => array(),
            ),
            Json::decode($response->getContent())
        );
    }

    public function testGetFolder()
    {
        $request = $this->newRequest('PUT');
        $request->setPathInfo('/admin/foo/bar/baz.txt');
        $request->setContentType('text/plain');
        $request->setContent('Hello World!');
        $response = $this->r->run($request);
        $this->assertEquals(200, $response->getStatusCode());

        $request = $this->newRequest('GET');
        $request->setPathInfo('/admin/foo/bar/');
        $response = $this->r->run($request);
        $this->assertEquals('application/ld+json', $response->getContentType());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('/1:[a-z0-9]+/i', $response->getHeader('ETag'));
        $folderData = Json::decode($response->getContent());
        $this->assertEquals(2, count($folderData));
        $this->assertEquals(1, count($folderData['items']));
        $this->assertEquals('http://remotestorage.io/spec/folder-description', $folderData['@context']);
        $this->assertRegexp('/1:[a-z0-9]+/i', $folderData['items']['baz.txt']['ETag']);
        $this->assertEquals('text/plain', $folderData['items']['baz.txt']['Content-Type']);
        $this->assertEquals(12, $folderData['items']['baz.txt']['Content-Length']);
    }

    public function testGetSameVersionDocument()
    {
        $request = $this->newRequest('PUT');
        $request->setPathInfo('/admin/foo/bar/baz.txt');
        $request->setContentType('text/plain');
        $request->setContent('Hello World!');
        $response = $this->r->run($request);
        $this->assertEquals(200, $response->getStatusCode());
        $documentVersion = $response->getHeader('ETag');
        $this->assertNotNull($documentVersion);

        $request = $this->newRequest('GET');
        $request->setPathInfo('/admin/foo/bar/baz.txt');
        $request->setHeader('If-None-Match', $documentVersion);
        $response = $this->r->run($request);
        $this->assertEquals(304, $response->getStatusCode());
    }

    public function testGetSameVersionFolder()
    {
        $request = $this->newRequest('PUT');
        $request->setPathInfo('/admin/foo/bar/baz.txt');
        $request->setContentType('text/plain');
        $request->setContent('Hello World!');
        $response = $this->r->run($request);

        $request = $this->newRequest('GET');
        $request->setPathInfo('/admin/foo/bar/');
        $response = $this->r->run($request);
        $folderVersion = $response->getHeader('ETag');

        $request = $this->newRequest('GET');
        $request->setPathInfo('/admin/foo/bar/');
        $request->setHeader('If-None-Match', $folderVersion);
        $response = $this->r->run($request);
        $this->assertEquals(304, $response->getStatusCode());
    }

    /**
     * @expectedException fkooman\Http\Exception\PreconditionFailedException
     * @expectedExceptionMessage version mismatch
     */
    public function testPutNonMatchingVersion()
    {
        $request = $this->newRequest('PUT');
        $request->setPathInfo('/admin/foo/bar/baz.txt');
        $request->setContentType('text/plain');
        $request->setContent('Hello World!');
        $response = $this->r->run($request);

        $request = $this->newRequest('PUT');
        $request->setPathInfo('/admin/foo/bar/baz.txt');
        $request->setHeader('If-Match', '"non-matching-version"');
        $request->setContentType('text/plain');
        $request->setContent('Hello New World!');
        $this->r->run($request);
    }

    /**
     * @expectedException fkooman\Http\Exception\PreconditionFailedException
     * @expectedExceptionMessage version mismatch
     */
    public function testDeleteNonMatchingVersion()
    {
        $request = $this->newRequest('PUT');
        $request->setPathInfo('/admin/foo/bar/baz.txt');
        $request->setContentType('text/plain');
        $request->setContent('Hello World!');
        $response = $this->r->run($request);

        $request = $this->newRequest('DELETE');
        $request->setPathInfo('/admin/foo/bar/baz.txt');
        $request->setHeader('If-Match', '"non-matching-version"');
        $this->r->run($request);
    }
}
