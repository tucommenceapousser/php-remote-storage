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

        $this->assertEquals(
            array(
                '/',
                '/foo/',
                '/foo/bar/'
            ),
            $this->document->putDocument($p, $d)
        );
    }

    public function testGetDocument()
    {
        $p = new Path("/foo/bar/baz");
        $d = 'Hello World!';

        $this->assertEquals(
            array(
                '/',
                '/foo/',
                '/foo/bar/'
            ),
            $this->document->putDocument($p, $d)
        );
        $this->assertEquals($d, $this->document->getDocument($p));
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

    public function testDeleteDocument()
    {
        $p = new Path("/foo/bar/baz");
        $d = 'Hello World!';

        $this->assertEquals(
            array(
                '/',
                '/foo/',
                '/foo/bar/'
            ),
            $this->document->putDocument($p, $d)
        );
        $this->assertEquals(
            array(
                '/foo/bar/baz'
            ),
            $this->document->deleteDocument($p)
        );
    }

    /**
     * @expectedException fkooman\RemoteStorage\Exception\DocumentMissingException
     */
    public function testDoubleDeleteDocument()
    {
        $p = new Path("/foo/bar/baz");
        $d = 'Hello World!';

        $this->assertEquals(
            array(
                '/',
                '/foo/',
                '/foo/bar/'
            ),
            $this->document->putDocument($p, $d)
        );
        $this->assertEquals(
            array(
                '/foo/bar/baz'
            ),
            $this->document->deleteDocument($p)
        );
        $this->document->deleteDocument($p);
    }

    public function testGetFolder()
    {
        $p = new Path("/foo/bar/baz/foo");
        $d = 'Hello World!';

        $this->assertEquals(
            array(
                '/',
                '/foo/',
                '/foo/bar/',
                '/foo/bar/baz/'
            ),
            $this->document->putDocument($p, $d)
        );

        $parentFolder = new Path($p->getParentFolder());
        $this->assertEquals(array("foo" => array("Content-Length" => 12)), $this->document->getFolder($parentFolder));

        $parentFolder = new Path($parentFolder->getParentFolder());
        $this->assertEquals(array("baz/" => array()), $this->document->getFolder($parentFolder));
    }

    public function testGetEmptyFolder()
    {
        $p = new Path("/foo/bar/baz/");
        $this->assertEquals(array(), $this->document->getFolder($p));
    }

    public function testRecursiveFolderDelete()
    {
        $p = new Path("/foo/bar/baz/foobar/foobaz");
        $d = 'Hello World!';
        $this->assertEquals(
            array(
                '/',
                '/foo/',
                '/foo/bar/',
                '/foo/bar/baz/',
                '/foo/bar/baz/foobar/'
            ),
            $this->document->putDocument($p, $d)
        );

        // now delete the document, the /foo/bar directory should be empty
        $this->assertEquals(
            array(
                '/foo/bar/baz/foobar/foobaz',
                '/foo/bar/baz/foobar/',
                '/foo/bar/baz/'
            ),
            $this->document->deleteDocument($p)
        );
        $this->assertEquals(array(), $this->document->getFolder(new Path("/foo/bar/")));
    }
}
