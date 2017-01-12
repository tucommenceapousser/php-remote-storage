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

namespace fkooman\RemoteStorage\OAuth;

use PHPUnit_Framework_TestCase;

class ScopeTest extends PHPUnit_Framework_TestCase
{
    public function testScope()
    {
        $s = new Scope('read write foo');
        $this->assertTrue($s->hasScopeToken('read'));
        $this->assertTrue($s->hasScopeToken('write'));
        $this->assertTrue($s->hasScopeToken('foo'));
        $this->assertFalse($s->hasScopeToken('bar'));
    }

    public function testEmptyScope()
    {
        $s = new Scope();
        $this->assertFalse($s->hasScopeToken('foo'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage invalid characters in scope token
     */
    public function testInvalidScopeToken()
    {
        $s = new Scope('€ $');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage scope token must be a non-empty string
     */
    public function testEmptyArrayScope()
    {
        $s = new Scope('foo  bar');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage argument must be string
     */
    public function testNonStringFromString()
    {
        $s = new Scope(5);
    }

    public function testOutput()
    {
        $s = new Scope('foo bar baz');
        $this->assertEquals('bar baz foo', $s->toString());
        $this->assertEquals('bar baz foo', $s);
        $this->assertEquals(
            [
                'bar',
                'baz',
                'foo',
            ],
            $s->toArray()
        );
    }

    public function testhasScope()
    {
        $scope = new Scope('foo bar baz');
        $verifyScope = new Scope('foo');
        $emptyScope = new Scope();
        $this->assertTrue($scope->hasScope($verifyScope));
        $this->assertFalse($verifyScope->hasScope($scope));
        $this->assertTrue($scope->hasScope($emptyScope));
        $this->assertFalse($emptyScope->hasScope($scope));
    }
}
