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

class Base64Test extends PHPUnit_Framework_TestCase
{
    public function testEncode()
    {
        $this->assertSame('Zm9v', Base64::encode('foo'));
    }

    public function testDecode()
    {
        $this->assertSame('foo', Base64::decode('Zm9v'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage invalid base64 string length
     */
    public function testInvalidDataLength()
    {
        Base64::decode('A');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage invalid base64 string
     */
    public function testInvalidDecode()
    {
        Base64::decode('+=');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage data must be string
     */
    public function testNonStringEncode()
    {
        Base64::encode(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage data must be string
     */
    public function testNonStringDecode()
    {
        Base64::decode(null);
    }
}
