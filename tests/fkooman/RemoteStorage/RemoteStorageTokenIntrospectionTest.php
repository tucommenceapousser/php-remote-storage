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

class RemoteStorageTokenIntrospectionTest extends PHPUnit_Framework_TestCase
{
    public function testReadScope()
    {
        $t = new RemoteStorageTokenIntrospection(
            array(
                "active" => true,
                "sub" => "admin",
                "scope" => "foo:r bar:rw"
            )
        );
        $this->assertTrue($t->hasWriteScope("bar"));
        $this->assertTrue($t->hasReadScope("bar"));
        $this->assertTrue($t->hasReadScope("foo"));
        $this->assertFalse($t->hasWriteScope("foo"));
    }
}
