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
use fkooman\RemoteStorage\Exception\NotFoundException;
use fkooman\RemoteStorage\Exception\PreconditionFailedException;
use fkooman\RemoteStorage\Exception\NotModifiedException;

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
            $service = new Service($request);
            $service->match(
                "GET",
                null,
                function ($pathInfo) use ($request) {
                    $path = new Path($pathInfo);
                    if ($path->getIsFolder()) {
                        // folder
                        $folderVersion = $this->remoteStorage->getVersion($path);
                        if (null === $folderVersion) {
                            // folder does not exist, so we just invent this
                            // ETag that will be the same for all empty folders
                            $folderVersion = 'e:7398243bf0d8b3c6c7e7ec618b3ee703';
                        }
                        $rsr = new RemoteStorageResponse(200, $folderVersion);
                        $rsr->setContent($this->remoteStorage->getFolder($path, $request->getHeader("If-Non-Match")));

                        return $rsr;
                    } else {
                        // document
                        $documentVersion = $this->remoteStorage->getVersion($path);
                        $documentContentType = $this->remoteStorage->getContentType($path);
                        $documentContent = $this->remoteStorage->getDocument($path);

                        $rsr = new RemoteStorageResponse(200, $documentVersion, $documentContentType);
                        $rsr->setContent($this->remoteStorage->getDocument($path, $request->getHeader("If-Non-Match")));

                        return $rsr;
                    }
                }
            );

            $service->match(
                "PUT",
                null,
                function ($pathInfo) use ($request) {
                    $path = new Path($pathInfo);
                    if ($path->getIsFolder()) {
                        // FIXME: use more generic exceptions?
                        throw new RemoteStorageRequestHandlerException("can not put a folder");
                    }

                    $x = $this->remoteStorage->putDocument($path, $request->getContentType(), $request->getContent(), $request->getHeader("If-Match"), $request->getHeader("If-Non-Match"));
                    $documentVersion = $this->remoteStorage->getVersion($path);
                    $rsr = new RemoteStorageResponse(200, $documentVersion, 'application/json');
                    $rsr->setContent($x);

                    return $rsr;
                }
            );

            $service->match(
                "DELETE",
                null,
                function ($pathInfo) use ($request) {
                    $path = new Path($pathInfo);
                    if ($path->getIsFolder()) {
                        // FIXME: use more generic exceptions?
                        throw new RemoteStorageRequestHandlerException("can not delete a folder");
                    }
                    // need to get the version before the delete
                    $documentVersion = $this->remoteStorage->getVersion($path);
                    $x = $this->remoteStorage->deleteDocument($path, $request->getHeader("If-Non-Match"));
                    $rsr = new RemoteStorageResponse(200, $documentVersion, 'application/json');
                    $rsr->setContent($x);

                    return $rsr;
                }
            );

            $service->match(
                "OPTIONS",
                null,
                function ($pathInfo) use ($request) {
                    return new OptionsResponse();
                }
            );

            return $service->run();
        } catch (NotFoundException $e) {
            return new JsonResponse(404);
        } catch (PreconditionFailedException $e) {
            return new JsonResponse(412);
        } catch (NotModifiedException $e) {
            return new JsonResponse(304);
        }
    }
}
