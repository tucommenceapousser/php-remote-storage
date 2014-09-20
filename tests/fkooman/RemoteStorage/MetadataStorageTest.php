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

class MetadataStorageTest extends PHPUnit_Framework_TestCase
{
    private $md;

    public function setUp()
    {
        $this->md = new MetadataStorage(
            new PDO(
                $GLOBALS['DB_DSN'],
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD']
            )
        );
        $this->md->initDatabase();
    }

    public function testCreateDocument()
    {
        $p = new Path("/foo/bar/baz.txt");
        $this->assertNull($this->md->getVersion($p));
        $this->md->updateDocument($p, "text/plain");
        $this->assertStringMatchesFormat('%s', $this->md->getVersion($p));
        $this->assertEquals("text/plain", $this->md->getContentType($p));
    }

    public function testUpdateDocument()
    {
        $p = new Path("/foo/bar/baz.txt");
        $this->assertNull($this->md->getVersion($p));
        $this->md->updateDocument($p, "text/plain");
        $beforeUpdateVersion = $this->md->getVersion($p);
        $this->assertStringMatchesFormat('%s', $beforeUpdateVersion);
        $this->assertEquals("text/plain", $this->md->getContentType($p));

        // the update
        $this->md->updateDocument($p, "application/json");
        $this->assertEquals("application/json", $this->md->getContentType($p));
        $afterUpdateVersion = $this->md->getVersion($p);
        $this->assertStringMatchesFormat('%s', $afterUpdateVersion);
        $this->assertNotEquals($beforeUpdateVersion, $afterUpdateVersion);
    }

    public function testDeleteDocument()
    {
        $p = new Path("/foo/bar/baz.txt");
        $this->assertNull($this->md->getVersion($p));
        $this->md->updateDocument($p, "text/plain");
        $this->assertStringMatchesFormat('%s', $this->md->getVersion($p));
        $this->assertEquals("text/plain", $this->md->getContentType($p));

        $this->md->deleteNode($p);
        $this->assertNull($this->md->getVersion($p));
    }

    public function testUpdateDeleteUpdate()
    {
        // version MUST NOT be reused
        $p = new Path("/foo/bar/baz.txt");
        $this->assertNull($this->md->getVersion($p));
        $this->md->updateDocument($p, "text/plain");
        $beforeDeleteVersion = $this->md->getVersion($p);
        $this->md->deleteNode($p);
        $this->md->updateDocument($p, "application/json");
        $this->assertNotEquals($beforeDeleteVersion, $this->md->getVersion($p));
    }

    public function testUpdateFolder()
    {
        $p = new Path("/foo/bar/baz/");
        $this->assertNull($this->md->getVersion($p));
        $this->md->updateFolder($p);
        $this->assertNotNull($this->md->getVersion($p));
    }

    /**
     * @expectedException fkooman\RemoteStorage\Exception\MetadataStorageException
     */
    public function testDeleteNonExistingNode()
    {
        $p = new Path("/foo/bar/baz.txt");
        $this->md->deleteNode($p);
    }

    /**
     * @expectedException fkooman\RemoteStorage\Exception\MetadataStorageException
     */
    public function testFolderUpdateOnNonFolder()
    {
        $p = new Path("/foo/bar/baz.txt");
        $this->md->updateFolder($p);
    }
}
