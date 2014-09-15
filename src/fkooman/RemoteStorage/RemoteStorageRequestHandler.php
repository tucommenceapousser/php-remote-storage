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
use fkooman\RemoteStorage\Exception\DocumentNotFoundException;

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
        try {
            $remoteStorage = &$this->remoteStorage;

            $service = new Service($request);
            $service->match(
                "GET",
                null,
                function ($pathInfo) use ($request, $remoteStorage) {
                    $path = new Path($pathInfo);
                    if ($path->getIsFolder()) {
                        // folder
                        $folderVersion = $remoteStorage->getVersion($path);
                        if (null === $folderVersion) {
                            $folderVersion = 'e:' . Utils::randomHex();
                        }
                        $rsr = new RemoteStorageResponse(200, $folderVersion);
                        $rsr->setContent($remoteStorage->getFolder($path));

                        return $rsr;
                    } else {
                        // document
                        $documentVersion = $remoteStorage->getVersion($path);
                        $documentContentType = $remoteStorage->getContentType($path);
                        $documentContent = $remoteStorage->getDocument($path);

                        $rsr = new RemoteStorageResponse(200, $documentVersion, $documentContentType);
                        $rsr->setContent($remoteStorage->getDocument($path));

                        return $rsr;
                    }
                }
            );

            $service->match(
                "PUT",
                null,
                function ($pathInfo) use ($request, $remoteStorage) {
                    $path = new Path($pathInfo);
                    if ($path->getIsFolder()) {
                        // FIXME: use more generic exceptions?
                        throw new RemoteStorageRequestHandlerException("can not PUT a folder");
                    }

                    $x = $remoteStorage->putDocument($path, $request->getContentType(), $request->getContent());
                    $documentVersion = $remoteStorage->getVersion($path);
                    $rsr = new RemoteStorageResponse(200, $documentVersion, 'application/json');
                    $rsr->setContent($x);

                    return $rsr;
                }
            );

            $service->match(
                "DELETE",
                null,
                function ($pathInfo) use ($request, $remoteStorage) {
                    $path = new Path($pathInfo);
                    if ($path->getIsFolder()) {
                        // FIXME: use more generic exceptions?
                        throw new RemoteStorageRequestHandlerException("can not DELETE a folder");
                    }
                    // need to get the version before the delete
                    $documentVersion = $remoteStorage->getVersion($path);
                    $x = $remoteStorage->deleteDocument($path);
                    $rsr = new RemoteStorageResponse(200, $documentVersion, 'application/json');
                    $rsr->setContent($x);

                    return $rsr;
                }
            );

            $service->match(
                "OPTIONS",
                null,
                function ($pathInfo) use ($request, $remoteStorage) {
                    return new OptionsResponse();
                }
            );

            return $service->run();
        } catch (DocumentNotFoundException $e) {
            return new JsonResponse(404);
        }
    }
}
