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

use fkooman\RemoteStorage\OAuth\AccessToken;
use PDO;
use PHPUnit_Framework_TestCase;

class PdoAccessTokenStorageTest extends PHPUnit_Framework_TestCase
{
    /** @var PdoAccessTokenStorage */
    private $storage;

    public function setUp()
    {
        $io = $this->getMockBuilder('fkooman\IO\IO')->getMock();
        $io->expects($this->any())->method('getRandom')->will($this->returnValue('112233ff'));

        $this->storage = new PdoAccessTokenStorage(
            new PDO(
                $GLOBALS['DB_DSN'],
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD']
            ),
            '',
            $io
        );
        $this->storage->initDatabase();
    }

    public function testInsertToken()
    {
        $accessToken = new AccessToken(
            'foo',
            'bar',
            123456789,
            'foo bar'
        );

        $this->assertSame('112233ff', $this->storage->storeAccessToken($accessToken));
    }

    public function testGetToken()
    {
        $accessToken = new AccessToken(
            'foo',
            'bar',
            123456789,
            'foo bar'
        );

        $this->assertSame('112233ff', $this->storage->storeAccessToken($accessToken));
        $this->assertSame('foo', $this->storage->retrieveAccessToken('112233ff')->getClientId());
    }

    public function testGetMissingToken()
    {
        $this->assertFalse($this->storage->retrieveAccessToken('112233ff'));
    }
}
