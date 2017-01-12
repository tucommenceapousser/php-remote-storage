<?php

/**
 * Copyright 2015 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\RemoteStorage\Base64;

use PHPUnit_Framework_TestCase;

class Base64UrlTest extends PHPUnit_Framework_TestCase
{
    public function testEncode()
    {
        // no padding
        $this->assertSame('SGVsbG8gV29ybGQh', Base64Url::encode('Hello World!'));

        // padding of 1
        $this->assertSame('SGVsbG8gV29ybGQhISE', Base64Url::encode('Hello World!!!'));

        // padding of 2
        $this->assertSame('IQ', Base64Url::encode('!'));
    }

    public function testDecode()
    {
        // no padding
        $this->assertSame('Hello World!', Base64Url::decode('SGVsbG8gV29ybGQh'));

        // padding of 1
        $this->assertSame('Hello World!!!', Base64Url::decode('SGVsbG8gV29ybGQhISE'));

        // padding of 2
        $this->assertSame('!', Base64Url::decode('IQ'));
    }

    public function testEncodeWithPlusAndSlash()
    {
        // plus is replaced by dash
        $this->assertSame('PD94bWwgdmVyc2lvbj0iMS4wIj8-', Base64Url::encode('<?xml version="1.0"?>'));
        // slash replaces by underscore
        $this->assertSame('c3ViamVjdHM_X2Q9MQ', Base64Url::encode('subjects?_d=1'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage invalid base64url string length
     */
    public function testInvalidDecode()
    {
        // non base64(url) string
        Base64Url::decode('A');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage data must be string
     */
    public function testNonStringEncode()
    {
        Base64Url::encode(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage data must be string
     */
    public function testNonStringDecode()
    {
        Base64Url::decode(null);
    }
}
