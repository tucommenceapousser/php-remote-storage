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

use fkooman\RemoteStorage\Http\Exception\HttpException;
use fkooman\RemoteStorage\Http\Request;
use fkooman\RemoteStorage\Http\Response;
use fkooman\RemoteStorage\Http\Service;
use fkooman\RemoteStorage\Http\ServiceModuleInterface;

class WebfingerModule implements ServiceModuleInterface
{
    /** @var string */
    private $serverMode;

    public function __construct($serverMode)
    {
        $this->serverMode = $serverMode;
    }

    public function init(Service $service)
    {
        $service->get(
            '/.well-known/webfinger',
            function (Request $request) {
                $resource = $request->getQueryParameter('resource');
                if (null === $resource) {
                    throw new HttpException('resource parameter missing', 400);
                }
                if (0 !== strpos($resource, 'acct:')) {
                    throw new HttpException('unsupported resource type', 400);
                }
                $userAddress = substr($resource, 5);
                $atPos = strpos($userAddress, '@');
                if (false === $atPos) {
                    throw new HttpException('invalid user address', 400);
                }
                $user = substr($userAddress, 0, $atPos);

                $webFingerData = [
                    'links' => [
                        [
                            'href' => sprintf('%s%s', $request->getRootUri(), $user),
                            'properties' => [
                                'http://remotestorage.io/spec/version' => 'draft-dejong-remotestorage-05',
                                'http://remotestorage.io/spec/web-authoring' => null,
                                'http://tools.ietf.org/html/rfc6749#section-4.2' => sprintf('%s_oauth/authorize?login_hint=%s', $request->getRootUri(), $user),
                                'http://tools.ietf.org/html/rfc6750#section-2.3' => 'true',
                                'http://tools.ietf.org/html/rfc7233' => 'development' !== $this->serverMode ? 'GET' : null,
                            ],
                            'rel' => 'http://tools.ietf.org/id/draft-dejong-remotestorage',
                        ],
                        // legacy -03 WebFinger response
                        [
                            'href' => sprintf('%s%s', $request->getRootUri(), $user),
                            'properties' => [
                                'http://remotestorage.io/spec/version' => 'draft-dejong-remotestorage-03',
                                'http://tools.ietf.org/html/rfc2616#section-14.16' => 'development' !== $this->serverMode ? 'GET' : false,
                                'http://tools.ietf.org/html/rfc6749#section-4.2' => sprintf('%s_oauth/authorize?login_hint=%s', $request->getRootUri(), $user),
                                'http://tools.ietf.org/html/rfc6750#section-2.3' => true,
                            ],
                            'rel' => 'remotestorage',
                        ],
                    ],
                ];

                $response = new Response(200, 'application/jrd+json');
                $response->addHeader('Access-Control-Allow-Origin', '*');
                $response->setBody(
                    json_encode($webFingerData)
                );

                return $response;
            }
        );
    }
}
