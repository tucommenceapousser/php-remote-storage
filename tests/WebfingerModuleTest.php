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

use fkooman\RemoteStorage\Http\Request;
use fkooman\RemoteStorage\Http\Service;
use PHPUnit_Framework_TestCase;

class WebfingerModuleTest extends PHPUnit_Framework_TestCase
{
    /** \fkooman\RemoteStorage\Http\Service */
    private $service;

    public function setUp()
    {
        $this->service = new Service();
        $this->service->addModule(new WebfingerModule('development'));
    }

    public function testRequest()
    {
        $request = new Request(
            [
                'REQUEST_METHOD' => 'GET',
                'SERVER_NAME' => 'localhost',
                'SERVER_PORT' => 80,
                'REQUEST_URI' => '/.well-known/webfinger?resource=acct:foo@www.example.org',
                'SCRIPT_NAME' => '/index.php',
            ],
            [
                'resource' => 'acct:foo@www.example.org',
            ]
        );

        $response = $this->service->run($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/jrd+json', $response->getHeader('Content-Type'));
        $this->assertSame('*', $response->getHeader('Access-Control-Allow-Origin'));
        $this->assertSame(
            '{"links":[{"href":"http:\/\/localhost\/foo","properties":{"http:\/\/remotestorage.io\/spec\/version":"draft-dejong-remotestorage-05","http:\/\/remotestorage.io\/spec\/web-authoring":null,"http:\/\/tools.ietf.org\/html\/rfc6749#section-4.2":"http:\/\/localhost\/_oauth\/authorize?login_hint=foo","http:\/\/tools.ietf.org\/html\/rfc6750#section-2.3":"true","http:\/\/tools.ietf.org\/html\/rfc7233":null},"rel":"http:\/\/tools.ietf.org\/id\/draft-dejong-remotestorage"},{"href":"http:\/\/localhost\/foo","properties":{"http:\/\/remotestorage.io\/spec\/version":"draft-dejong-remotestorage-03","http:\/\/tools.ietf.org\/html\/rfc2616#section-14.16":false,"http:\/\/tools.ietf.org\/html\/rfc6749#section-4.2":"http:\/\/localhost\/_oauth\/authorize?login_hint=foo","http:\/\/tools.ietf.org\/html\/rfc6750#section-2.3":true},"rel":"remotestorage"}]}',
            $response->getBody()
        );
    }
}
