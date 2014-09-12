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

class MetadataTest extends PHPUnit_Framework_TestCase
{
    private $md;

    public function setUp()
    {
        $this->md = new Metadata(
            new PDO(
                $GLOBALS['DB_DSN'],
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD']
            )
        );
        $this->md->initDatabase();
    }

    public function testSetVersion()
    {
        $p = new Path("/foo/bar/baz.txt");
        $this->assertNull($this->md->getVersion($p));
        $this->assertTrue($this->md->setMetadata($p, "text/plain", "abc123"));
        $this->assertEquals("abc123", $this->md->getVersion($p));
        $this->assertEquals("text/plain", $this->md->getType($p));
    }

    public function testUpdateVersion()
    {
        $p = new Path("/foo/bar/baz.txt");
        $this->assertNull($this->md->getVersion($p));
        $this->assertTrue($this->md->setMetadata($p, "text/plain", "abc123"));
        $this->assertEquals("abc123", $this->md->getVersion($p));
        $this->assertEquals("text/plain", $this->md->getType($p));

        // the update
        $this->assertTrue($this->md->setMetadata($p, "application/json", "foo123"));
        $this->assertEquals("foo123", $this->md->getVersion($p));
        $this->assertEquals("application/json", $this->md->getType($p));
    }
}
