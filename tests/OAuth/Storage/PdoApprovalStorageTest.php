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

use fkooman\RemoteStorage\OAuth\Approval;
use PDO;
use PHPUnit_Framework_TestCase;

class PdoApprovalStorageTest extends PHPUnit_Framework_TestCase
{
    /** @var PdoApprovalStorage */
    private $storage;

    public function setUp()
    {
        $this->storage = new PdoApprovalStorage(
            new PDO(
                $GLOBALS['DB_DSN'],
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD']
            )
        );
        $this->storage->initDatabase();
    }

    public function testStoreApproval()
    {
        $approval = new Approval(
            'user',
            'test-client',
            'https://example.org/cb',
            'code',
            'foo bar'
        );
        $this->assertTrue($this->storage->storeApproval($approval));
    }

    public function testIsApproved()
    {
        $approval = new Approval(
            'user',
            'test-client',
            'https://example.org/cb',
            'code',
            'foo bar'
        );
        $this->assertTrue($this->storage->storeApproval($approval));
        $this->assertTrue($this->storage->isApproved($approval));
    }

    public function testIsNotApproved()
    {
        $approval = new Approval(
            'user',
            'test-client',
            'https://example.org/cb',
            'code',
            'foo bar'
        );
        $this->assertFalse($this->storage->isApproved($approval));
    }
}
