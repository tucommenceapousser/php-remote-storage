<?php

declare(strict_types=1);

/*
 * php-remote-storage - PHP remoteStorage implementation
 *
 * Copyright: 2016 SURFnet
 * Copyright: 2022 François Kooman <fkooman@tuxed.net>
 *
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace fkooman\RemoteStorage;

use fkooman\RemoteStorage\Http\Exception\HttpException;
use fkooman\RemoteStorage\Http\Request;
use fkooman\RemoteStorage\Http\Response;

class WebfingerModule
{
    /** @var string */
    private $serverMode;

    /**
     * @param string $serverMode
     */
    public function __construct($serverMode)
    {
        $this->serverMode = $serverMode;
    }

    public function getWebfinger(Request $request)
    {
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
                        'http://tools.ietf.org/html/rfc6749#section-4.2' => sprintf('%sauthorize?login_hint=%s', $request->getRootUri(), $user),
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
                        'http://tools.ietf.org/html/rfc6749#section-4.2' => sprintf('%sauthorize?login_hint=%s', $request->getRootUri(), $user),
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
}
