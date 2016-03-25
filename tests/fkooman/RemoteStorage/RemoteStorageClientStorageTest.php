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

class RemoteStorageClientStorageTest extends PHPUnit_Framework_TestCase
{
    public function testBasic()
    {
        $r = new RemoteStorageClientStorage();
        $client = $r->getClient('http://example.org', 'token', 'http://example.org/cb', 'foo bar');
        $this->assertEquals('http://example.org', $client->getClientId());
    }

    public function testBasicNonDefaultPort()
    {
        $r = new RemoteStorageClientStorage();
        $client = $r->getClient('http://example.org:8080', 'token', 'http://example.org:8080/cb', 'foo bar');
        $this->assertEquals('http://example.org:8080', $client->getClientId());
    }

    public function testBasicOtherScheme()
    {
        $r = new RemoteStorageClientStorage();
        $client = $r->getClient('app://callback', 'token', 'app://callback/bar', 'foo bar');
        $this->assertEquals('app://callback', $client->getClientId());
    }
}
