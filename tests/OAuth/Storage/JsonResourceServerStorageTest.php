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

use PHPUnit_Framework_TestCase;

class JsonResourceServerStorageTest extends PHPUnit_Framework_TestCase
{
    public function testGet()
    {
        $resourceServerStorage = new JsonResourceServerStorage(__DIR__.'/data/resource_servers.json');
        $resourceServer = $resourceServerStorage->getResourceServer('my_resource_server');
        $this->assertSame('my_resource_server', $resourceServer->getResourceServerId());
        $this->assertSame('$2y$10$cG3iFTTpitGAHYyci8bII.68.uRwvmSpCTvEfVmDwka5E2132XmAC', $resourceServer->getSecret());
        $this->assertSame('post', $resourceServer->getScope());
    }

    public function testGetNonExisting()
    {
        $resourceServerStorage = new JsonResourceServerStorage(__DIR__.'/data/resource_servers.json');
        $this->assertFalse($resourceServerStorage->getResourceServer('non_existing'));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage error reading file
     */
    public function testMissingFile()
    {
        $resourceServerStorage = new JsonResourceServerStorage(__DIR__.'/data/missing.json');
        $resourceServerStorage->getResourceServer('foo');
    }
}
