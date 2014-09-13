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

class RemoteStorageTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $md = new Metadata(
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
        $d = $this->r->getDocumentData($p);
        $v = $this->r->getDocumentVersion($p);

        $this->assertEquals('Hello World!', $d);
        $this->assertEquals(1, $v);
    }

    public function testDeleteDocument()
    {
        $this->assertTrue(true);
    }
}
