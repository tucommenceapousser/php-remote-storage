<?php

/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
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

namespace fkooman\RemoteStorage\Config;

use PHPUnit_Framework_TestCase;

class YamlFileTest extends PHPUnit_Framework_TestCase
{
    public function testReadConfig()
    {
        $yamlFile = new YamlFile(__DIR__.'/test.yaml');
        $this->assertSame(
            [
                'foo' => 'bar',
                'Bar' => [
                    'a' => 'b',
                    'b' => 'c',
                ],
            ],
            $yamlFile->readConfig()
        );
    }

    public function testReadMultiConfig()
    {
        $yamlFile = new YamlFile(
            [
                __DIR__.'/test_missing.yaml',
                __DIR__.'/test.yaml',
            ]
        );

        $this->assertSame(
            [
                'foo' => 'bar',
                'Bar' => [
                    'a' => 'b',
                    'b' => 'c',
                ],
            ],
            $yamlFile->readConfig()
        );
    }

    public function testReadMultiExistConfigTakesFirst()
    {
        $yamlFile = new YamlFile(
            [
                __DIR__.'/test_2.yaml',
                __DIR__.'/test.yaml',
            ]
        );

        $this->assertSame(
            [
                'foo' => 'baz',
                'Bar' => [
                    'a' => 'c',
                    'b' => 'd',
                ],
            ],
            $yamlFile->readConfig()
        );
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testReadConfigFail()
    {
        $yamlFile = new YamlFile(__DIR__.'/test_missing.yaml');
        $yamlFile->readConfig();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testReadMultiConfigFail()
    {
        $yamlFile = new YamlFile(
            [
                __DIR__.'/test_missing.yaml',
                __DIR__.'/test_missing_2.yaml',
            ]
        );
        $yamlFile->readConfig();
    }

    public function testWriteConfig()
    {
        $configData = [
            'foo' => 'bar',
        ];

        $yamlFile = new YamlFile(tempnam(sys_get_temp_dir(), 'tst'));
        $yamlFile->writeConfig($configData);
        $this->assertSame(
            $configData,
            $yamlFile->readConfig()
        );
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage unable to write configuration file(s) "/foo"
     */
    public function testWriteConfigFail()
    {
        $yamlFile = new YamlFile('/foo');
        $yamlFile->writeConfig(['foo' => 'bar']);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage unable to write configuration file(s) "/foo,/bar"
     */
    public function testWriteMultiConfigFail()
    {
        $yamlFile = new YamlFile(['/foo', '/bar']);
        $yamlFile->writeConfig(['foo' => 'bar']);
    }
}
