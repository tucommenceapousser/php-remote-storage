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

use fkooman\RemoteStorage\OAuth\AuthorizationCode;
use PDO;
use PHPUnit_Framework_TestCase;

class PdoAuthorizationCodeStorageTest extends PHPUnit_Framework_TestCase
{
    /** @var PdoAuthorizationCodeStorage */
    private $storage;

    public function setUp()
    {
        $io = $this->getMockBuilder('fkooman\IO\IO')->getMock();
        $io->expects($this->any())->method('getRandom')->will($this->returnValue('112233ff'));

        $this->storage = new PdoAuthorizationCodeStorage(
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

    public function testInsertCode()
    {
        $authorizationCode = new AuthorizationCode(
            'foo',
            'bar',
            123456789,
            'https://example.org/cb',
            'foo bar'
        );

        $this->assertSame('112233ff', $this->storage->storeAuthorizationCode($authorizationCode));
    }

    public function testGetCode()
    {
        $authorizationCode = new AuthorizationCode(
            'foo',
            'bar',
            123456789,
            'https://example.org/cb',
            'foo bar'
        );

        $this->assertSame('112233ff', $this->storage->storeAuthorizationCode($authorizationCode));
        $this->assertSame('foo', $this->storage->retrieveAuthorizationCode('112233ff')->getClientId());
    }

    public function testGetMissingCode()
    {
        $this->assertFalse($this->storage->retrieveAuthorizationCode('112233ff'));
    }

    public function testCodeLog()
    {
        $this->assertTrue($this->storage->isFreshAuthorizationCode('112233ff'));
        $this->assertFalse($this->storage->isFreshAuthorizationCode('112233ff'));
    }
}
