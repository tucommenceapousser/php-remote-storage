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
use PHPUnit_Framework_TestCase;
use fkooman\RemoteStorage\Exception\DocumentNotFoundException;

class RemoteStorageTest extends PHPUnit_Framework_TestCase
{
    /** @var fkooman\RemoteStorage\RemoteStorage */
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
        $this->r = new RemoteStorage($md, $document);
    }

    public function testPutDocument()
    {
        $p = new Path("/admin/messages/foo/hello.txt");
        $this->r->putDocument($p, 'text/plain', 'Hello World!');
        $this->assertEquals('Hello World!', $this->r->getDocument($p));
        $this->assertRegexp('/1:[a-z0-9]+/i', $this->r->getVersion($p));
    }

    public function testPutMultipleDocuments()
    {
        $p1 = new Path("/admin/messages/foo/hello.txt");
        $p2 = new Path("/admin/messages/foo/bar.txt");
        $p3 = new Path("/admin/messages/foo/");
        $p4 = new Path("/admin/messages/");
        $p5 = new Path("/admin/");
        $this->r->putDocument($p1, 'text/plain', 'Hello World!');
        $this->r->putDocument($p2, 'text/plain', 'Hello Foo!');
        $this->assertEquals('Hello World!', $this->r->getDocument($p1));
        $this->assertRegexp('/1:[a-z0-9]+/i', $this->r->getVersion($p1));
        $this->assertEquals('Hello Foo!', $this->r->getDocument($p2));
        $this->assertRegexp('/1:[a-z0-9]+/i', $this->r->getVersion($p2));
        // all parent directories should have version 2 now
        $this->assertRegexp('/2:[a-z0-9]+/i', $this->r->getVersion($p3));
        $this->assertRegexp('/2:[a-z0-9]+/i', $this->r->getVersion($p4));
        $this->assertRegexp('/2:[a-z0-9]+/i', $this->r->getVersion($p5));
    }

    public function testDeleteDocument()
    {
        $p = new Path("/admin/messages/foo/baz.txt");
        $this->r->putDocument($p, 'text/plain', 'Hello World!');
        $this->r->deleteDocument($p);
        $this->assertNull($this->r->getVersion($p));
        try {
            $this->r->getDocument($p);
            $this->assertTrue(false);
        } catch (DocumentNotFoundException $e) {
            $this->assertTrue(true);
        }
        // directory should also not be there anymore
        $p = new Path("/admin/messages/foo/");
        $this->assertNull($this->r->getVersion($p));
    }

    public function testDeleteMultipleDocuments()
    {
        $p1 = new Path("/admin/messages/foo/baz.txt");
        $p2 = new Path("/admin/messages/foo/bar.txt");
        $p3 = new Path("/admin/messages/foo/");
        $p4 = new Path("/admin/messages/");
        $p5 = new Path("/admin/");

        $this->r->putDocument($p1, 'text/plain', 'Hello Baz!');
        $this->r->putDocument($p2, 'text/plain', 'Hello Bar!');
        $this->r->deleteDocument($p1);
        $this->assertNull($this->r->getVersion($p1));
        $this->assertRegexp('/1:[a-z0-9]+/i', $this->r->getVersion($p2));
        $this->assertRegexp('/2:[a-z0-9]+/i', $this->r->getVersion($p3));
        $this->assertRegexp('/2:[a-z0-9]+/i', $this->r->getVersion($p4));
        $this->assertRegexp('/2:[a-z0-9]+/i', $this->r->getVersion($p5));
    }

    public function testGetFolder()
    {
        $p1 = new Path("/admin/messages/foo/baz.txt");
        $p2 = new Path("/admin/messages/foo/bar.txt");
        $p3 = new Path("/admin/messages/foo/");
        $this->r->putDocument($p1, 'text/plain', 'Hello Baz!');
        $this->r->putDocument($p2, 'text/plain', 'Hello Bar!');
        $this->r->putDocument($p2, 'text/plain', 'Hello Updated Bar!');

        $folderData = $this->r->getFolder($p3);
        $this->assertEquals(2, count($folderData));
        $this->assertEquals(2, count($folderData['items']));
        $this->assertEquals('http://remotestorage.io/spec/folder-description', $folderData['@context']);
        $this->assertRegexp('/2:[a-z0-9]+/i', $folderData['items']['bar.txt']['ETag']);
        $this->assertEquals('text/plain', $folderData['items']['bar.txt']['Content-Type']);
        $this->assertEquals(18, $folderData['items']['bar.txt']['Content-Length']);
        $this->assertRegexp('/1:[a-z0-9]+/i', $folderData['items']['baz.txt']['ETag']);
        $this->assertEquals('text/plain', $folderData['items']['baz.txt']['Content-Type']);
        $this->assertEquals(10, $folderData['items']['baz.txt']['Content-Length']);
        $this->assertRegexp('/3:[a-z0-9]+/i', $this->r->getVersion($p3));
    }

    public function testGetFolderWithFolder()
    {
        $p1 = new Path("/admin/messages/foo/baz.txt");
        $p2 = new Path("/admin/messages/foo/foobar/bar.txt");
        $p3 = new Path("/admin/messages/foo/");
        $this->r->putDocument($p1, 'text/plain', 'Hello Baz!');
        $this->r->putDocument($p2, 'text/plain', 'Hello Bar!');
        $this->r->putDocument($p2, 'text/plain', 'Hello Updated Bar!');

        $folderData = $this->r->getFolder($p3);
        $this->assertEquals(2, count($folderData));
        $this->assertEquals(2, count($folderData['items']));
        $this->assertEquals('http://remotestorage.io/spec/folder-description', $folderData['@context']);
        $this->assertRegexp('/2:[a-z0-9]+/i', $folderData['items']['foobar/']['ETag']);
        $this->assertRegexp('/1:[a-z0-9]+/i', $folderData['items']['baz.txt']['ETag']);
        $this->assertEquals('text/plain', $folderData['items']['baz.txt']['Content-Type']);
        $this->assertEquals(10, $folderData['items']['baz.txt']['Content-Length']);

        $this->assertRegexp('/3:[a-z0-9]+/i', $this->r->getVersion($p3));
    }
}
