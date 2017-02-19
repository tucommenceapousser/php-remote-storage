<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Lesser General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Lesser General Public License for more details.
 *
 *  You should have received a copy of the GNU Lesser General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace fkooman\RemoteStorage;

use DateTime;
use fkooman\RemoteStorage\Http\Request;
use PDO;
use PHPUnit_Framework_TestCase;

class ControllerTest extends PHPUnit_Framework_TestCase
{
    /** @var Controller */
    private $controller;

    public function setUp()
    {
        // set up the directory structure
        $projectDir = dirname(__DIR__);
        $tmpDir = sprintf('%s/%s', sys_get_temp_dir(), bin2hex(random_bytes(16)));
        mkdir($tmpDir);
        mkdir(sprintf('%s/config', $tmpDir));
        copy(
            sprintf('%s/config/server.dev.yaml.example', $projectDir),
            sprintf('%s/config/server.yaml', $tmpDir)
        );

        // copy the templates
        mkdir(sprintf('%s/views', $tmpDir));
        foreach (glob(sprintf('%s/views/*', $projectDir)) as $templateFile) {
            copy(
                $templateFile,
                sprintf('%s/views/%s', $tmpDir, basename($templateFile))
            );
        }

        mkdir(sprintf('%s/data', $tmpDir));
        $db = new PDO(sprintf('sqlite:%s/data/rs.sqlite', $tmpDir));
        $metadataStorage = new MetadataStorage($db);
        $metadataStorage->init();

        $remoteStorage = new RemoteStorage(
            new MetadataStorage($db),
            new DocumentStorage(sprintf('%s/data/storage', $tmpDir))
        );
        $remoteStorage->putDocument(new Path('/foo/public/hello.txt'), 'text/plain', 'Hello World!');

        $random = $this->getMockBuilder('\fkooman\RemoteStorage\RandomInterface')->getMock();
        $random->method('get')->will($this->onConsecutiveCalls('random_1', 'random_2'));
        $session = $this->getMockBuilder('\fkooman\RemoteStorage\Http\SessionInterface')->getMock();

        $this->controller = new Controller($tmpDir, $session, $random, new DateTime('2016-01-01'));
    }

    public function testGetPublicFile()
    {
        $request = new Request(
            [
                'SERVER_NAME' => 'example.org',
                'SERVER_PORT' => 80,
                'REQUEST_URI' => '/foo/public/hello.txt',
                'SCRIPT_NAME' => '/index.php',
                'REQUEST_METHOD' => 'GET',
            ],
            [],
            [],
            ''
        );
        $response = $this->controller->run($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello World!', $response->getBody());
    }

    public function testGetFile()
    {
    }

    public function testPutFile()
    {
    }

    public function testDeleteFile()
    {
    }

    public function testGetAuthorizationNotLoggedIn()
    {
        $queryParameters = [
            'client_id' => 'https://app.example.org',
            'redirect_uri' => 'https://app.example.org/callback.html',
            'response_type' => 'token',
            'scope' => 'foo:r',
        ];

        $request = new Request(
            [
                'SERVER_NAME' => 'example.org',
                'SERVER_PORT' => 80,
                'REQUEST_URI' => sprintf('/authorize?%s', http_build_query($queryParameters)),
                'SCRIPT_NAME' => '/index.php',
                'REQUEST_METHOD' => 'GET',
            ],
            $queryParameters,
            [],
            ''
        );
        $response = $this->controller->run($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            file_get_contents(
                sprintf('%s/data/testGetAuthorizationNotLoggedIn', __DIR__)
            ),
            $response->getBody()
        );
    }

    public function testPostAuthorizationNotLoggedIn()
    {
    }

    public function testGetAuthorizationLoggedIn()
    {
    }

    public function testPostAuthorization()
    {
    }

    public function testGetWebfinger()
    {
    }
}
