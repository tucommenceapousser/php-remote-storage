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

class ArrayClientStorageTest extends PHPUnit_Framework_TestCase
{
    public function testGet()
    {
        $clientStorage = new ArrayClientStorage(
            json_decode(file_get_contents(__DIR__.'/data/clients.json'), true)
        );
        $client = $clientStorage->getClient('my_client', 'code', 'https://example.org/cb', 'read');
        $this->assertSame('my_client', $client->getClientId());
        $this->assertSame('$2y$10$cG3iFTTpitGAHYyci8bII.68.uRwvmSpCTvEfVmDwka5E2132XmAC', $client->getSecret());
        $this->assertSame('read', $client->getScope());
        $this->assertSame('https://example.org/cb', $client->getRedirectUri());
        $this->assertSame('code', $client->getResponseType());
    }

    public function testGetNonExisting()
    {
        $clientStorage = new ArrayClientStorage(
            json_decode(file_get_contents(__DIR__.'/data/clients.json'), true)
        );
        $this->assertFalse($clientStorage->getClient('non_existing', 'code', 'https://example.org/cb', 'read'));
    }
}
