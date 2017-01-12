<?php

/**
 *  Copyright 2015 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\RemoteStorage\OAuth\Storage;

use PDO;
use PHPUnit_Framework_TestCase;

class PdoResourceServerStorageTest extends PHPUnit_Framework_TestCase
{
    /** @var PdoResourceServerStorage */
    private $storage;

    public function setUp()
    {
        $db = new PDO(
            $GLOBALS['DB_DSN'],
            $GLOBALS['DB_USER'],
            $GLOBALS['DB_PASSWD']
        );

        $this->storage = new PdoResourceServerStorage($db);
        $this->storage->initDatabase();

        // add a resource server
        $db->query(
            'INSERT INTO resource_server (id, scope, secret) VALUES("foo", "read", "$2y$10$vrHBaF01p9yqbOksTrR7aueltwHS4WA.dCktSHlrjDcFub.rKZuSa")'
        );
    }

    public function testGetResourceServer()
    {
        $resourceServer = $this->storage->getResourceServer('foo');
        $this->assertSame('foo', $resourceServer->getResourceServerId());
        $this->assertSame('read', $resourceServer->getScope());
        $this->assertSame('$2y$10$vrHBaF01p9yqbOksTrR7aueltwHS4WA.dCktSHlrjDcFub.rKZuSa', $resourceServer->getSecret());
    }

    public function testNonExistingResourceServer()
    {
        $this->assertFalse($this->storage->getResourceServer('bar'));
    }
}
