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

use fkooman\Http\Request;
use fkooman\Http\JsonResponse;
use fkooman\OAuth\ResourceServer\TokenIntrospection;
use fkooman\Rest\Service;

class RemoteStorageRequestHandler
{
    /** @var fkooman\RemoteStorage\RemoteStorage */
    private $remoteStorage;

    /** @var fkooman\OAuth\ResourceServer\TokenIntrospection */
    private $tokenIntrospection;

    public function __construct(RemoteStorage $remoteStorage, TokenIntrospection $tokenIntrospection)
    {
        $this->remoteStorage = $remoteStorage;
        $this->tokenIntrospection = $tokenIntrospection;
    }

    public function handleRequest(Request $request)
    {
        $remoteStorage = &$this->remoteStorage;

        $service = new Service($request);
        $service->match(
            "GET",
            "/:pathInfo+/",
            function ($pathInfo) use ($request, $remoteStorage) {
                $jsonResponse = new JsonResponse();
                $jsonRespone->setContent(
                    $remoteStorage->getFolder(
                        new Path($request->getPathInfo()),
                        $request->getHeader("If-None-Match")
                    )
                );

                return $jsonResponse;
            }
        );

        $service->match(
            "GET",
            "/:pathInfo+",
            function ($pathInfo) use ($request, $remoteStorage) {
                $jsonResponse = new JsonResponse();
                $jsonResponse->setContent(
                    $remoteStorage->getDocument(
                        new Path($request->getPathInfo()),
                        $request->getHeader("If-None-Match")
                    )
                );

                return $jsonResponse;
            }
        );

        $service->match(
            "PUT",
            "/:pathInfo+",
            function ($pathInfo) use ($request, $remoteStorage) {
                $jsonResponse = new JsonResponse();
                $jsonResponse->setContent(
                    $remoteStorage->putDocument(
                        new Path($request->getPathInfo()),
                        $request->getContent(),
                        $request->getHeader("Content-Type"),
                        $request->getHeader("If-Match"),
                        $request->getHeader("If-None-Match")
                    )
                );

                return $jsonResponse;
            }
        );

        $service->match(
            "DELETE",
            "/:pathInfo+",
            function ($pathInfo) use ($request, $remoteStorage) {
                $jsonResponse = new JsonResponse();
                $jsonResponse->setContent(
                    $remoteStorage->deleteDocument(
                        new Path($request->getPathInfo()),
                        $request->getHeader("If-Match")
                    )
                );

                return $jsonResponse;
            }
        );

        $service->match(
            "OPTIONS",
            "/:pathInfo+(/)",
            function ($pathInfo) use ($request, $remoteStorage) {
                return new OptionsResponse();
            }
        );

        return $service->run();
    }
}
