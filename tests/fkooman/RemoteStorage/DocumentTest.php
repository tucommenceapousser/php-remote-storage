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

use PHPUnit_Framework_TestCase;
use fkooman\RemoteStorage\Exception\DocumentMissingException;

class DocumentTest extends PHPUnit_Framework_TestCase
{
    private $document;

    public function setUp()
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
        mkdir($tempFile);
        $this->document = new Document($tempFile);
    }

    public function testPutDocument()
    {
        $p = new Path("/foo/bar/baz");
        $d = 'Hello World!';

        $this->document->putDocument($p, $d);
        $this->assertEquals($d, $this->document->getDocument($p));

        // first time delete must work
        $this->document->deleteDocument($p);

        // second time delte must not work
        try {
            $this->document->deleteDocument($p);
            $this->assertTrue(false);
        } catch (DocumentMissingException $e) {
        }
    }

    /**
     * @expectedException fkooman\RemoteStorage\Exception\DocumentMissingException
     */
    public function testGetMissingDocument()
    {
        $p = new Path("/foo/bar/baz/foo");
        $this->document->getDocument($p);
    }

    /**
     * @expectedException fkooman\RemoteStorage\Exception\DocumentMissingException
     */
    public function testDeleteMissingDocument()
    {
        $p = new Path("/foo/bar/baz/foo");
        $this->document->deleteDocument($p);
    }
}
