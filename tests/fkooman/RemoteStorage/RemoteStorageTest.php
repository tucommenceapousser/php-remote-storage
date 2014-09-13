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
use fkooman\OAuth\ResourceServer\TokenIntrospection;
use fkooman\RemoteStorage\Exception\DocumentMissingException;

class RemoteStorageTest extends PHPUnit_Framework_TestCase
{
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
        $document = new Document($tempFile);

        $introspect = new TokenIntrospection(
            array(
                "active" => true,
                "sub" => "foo"
            )
        );

        $this->r = new RemoteStorage($md, $document, $introspect);
    }

    public function testPutDocument()
    {
        $p = new Path("/foo/bar/baz.txt");
        $this->r->putDocument($p, 'text/plain', 'Hello World!');
        $this->assertEquals('Hello World!', $this->r->getDocumentData($p));
        $this->assertEquals(1, $this->r->getDocumentVersion($p));
    }

    public function testDeleteDocument()
    {
        $p = new Path("/foo/bar/baz.txt");
        $this->r->putDocument($p, 'text/plain', 'Hello World!');
        $this->r->deleteDocument($p);
        $this->assertNull($this->r->getDocumentVersion($p));
        try {
            $this->r->getDocumentData($p);
            $this->assertTrue(false);
        } catch (DocumentMissingException $e) {
            $this->assertTrue(true);
        }
    }
}
