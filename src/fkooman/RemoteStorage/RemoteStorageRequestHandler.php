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
use fkooman\Rest\Service;
use fkooman\OAuth\ResourceServer\ResourceServer;
use fkooman\OAuth\ResourceServer\ResourceServerException;
use fkooman\OAuth\ResourceServer\TokenIntrospection;
use fkooman\OAuth\Common\Scope;

class RemoteStorageRequestHandler
{
    /** @var fkooman\RemoteStorage\RemoteStorage */
    private $remoteStorage;

    /** @var fkooman\OAuth\ResourceServer\ResourceServer */
    private $resourceServer;

    public function __construct(RemoteStorage $remoteStorage, ResourceServer $resourceServer)
    {
        $this->remoteStorage = $remoteStorage;
        $this->resourceServer = $resourceServer;
    }

    public function handleRequest(Request $request)
    {
        $this->resourceServer->setAuthorizationHeader($request->getHeader("Authorization"));
        $this->resourceServer->setAccessTokenQueryParameter($request->getQueryParameter("access_token"));

        $service = new Service($request);
        $service->match(array("GET", "HEAD"),
            "*",
            function ($pathInfo) use ($request) {
                $path = new Path($pathInfo);
                if ($path->getIsFolder()) {
                    // folder
                    $tokenIntrospection = $this->resourceServer->verifyToken();
                    if ($path->getUserId() !== $tokenIntrospection->getSub()) {
                        throw new UnauthorizedException("path does not match authorized subject");
                    }
                    if (!$this->hasReadScope($tokenIntrospection, $path->getModuleName())) {
                        throw new UnauthorizedException("path does not match authorized scope");
                    }
                    $folderVersion = $this->remoteStorage->getVersion($path);
                    if (null === $folderVersion) {
                        // folder does not exist, so we just invent this
                        // ETag that will be the same for all empty folders
                        $folderVersion = '"e:7398243bf0d8b3c6c7e7ec618b3ee703"';
                    }
                    $rsr = new RemoteStorageResponse($request, 200, $folderVersion);
                    if ("GET" === $request->getRequestMethod()) {
                        $rsr->setContent(
                            $this->remoteStorage->getFolder(
                                $path,
                                $this->stripQuotes(
                                    $request->getHeader("If-None-Match")
                                )
                            )
                        );
                    }

                    return $rsr;
                } else {
                    // document
                    if (!$path->getIsPublic()) {
                        $tokenIntrospection = $this->resourceServer->verifyToken();
                        if ($path->getUserId() !== $tokenIntrospection->getSub()) {
                            throw new UnauthorizedException("path does not match authorized subject");
                        }
                        if (!$this->hasReadScope($tokenIntrospection, $path->getModuleName())) {
                            throw new UnauthorizedException("path does not match authorized scope");
                        }
                    }
                    $documentVersion = $this->remoteStorage->getVersion($path);
                    $documentContentType = $this->remoteStorage->getContentType($path);
                    $documentContent = $this->remoteStorage->getDocument($path);

                    $rsr = new RemoteStorageResponse($request, 200, $documentVersion, $documentContentType);
                    if ("GET" === $request->getRequestMethod()) {
                        $rsr->setContent(
                            $this->remoteStorage->getDocument(
                                $path,
                                $this->stripQuotes(
                                    $request->getHeader("If-None-Match")
                                )
                            )
                        );
                    }

                    return $rsr;
                }
            }
        );

        $service->put(
            "*",
            function ($pathInfo) use ($request) {
                $path = new Path($pathInfo);

                $tokenIntrospection = $this->resourceServer->verifyToken();

                if ($path->getUserId() !== $tokenIntrospection->getSub()) {
                    // FIXME: should be Forbidden?
                    throw new UnauthorizedException("path does not match authorized subject");
                }
                if (!$this->hasWriteScope($tokenIntrospection, $path->getModuleName())) {
                    // FIXME: should throw ResourceServerException ???
                    throw new UnauthorizedException("path does not match authorized scope");
                }

                if ($path->getIsFolder()) {
                    // FIXME: use more generic exceptions?
                    throw new BadRequestException("can not put a folder");
                }
                if (null === $request->getContentType()) {
                    throw new BadRequestException("Content-Type not specified");
                }

                $x = $this->remoteStorage->putDocument(
                    $path,
                    $request->getContentType(),
                    $request->getContent(),
                    $this->stripQuotes(
                        $request->getHeader("If-Match")
                    ),
                    $this->stripQuotes(
                        $request->getHeader("If-None-Match")
                    )
                );
                $documentVersion = $this->remoteStorage->getVersion($path);
                $rsr = new RemoteStorageResponse($request, 200, $documentVersion, 'application/json');
                $rsr->setContent($x);

                return $rsr;
            }
        );

        $service->delete(
            "*",
            function ($pathInfo) use ($request) {
                $path = new Path($pathInfo);

                $tokenIntrospection = $this->resourceServer->verifyToken();

                if ($path->getUserId() !== $tokenIntrospection->getSub()) {
                    throw new UnauthorizedException("path does not match authorized subject");
                }
                if (!$this->hasWriteScope($tokenIntrospection, $path->getModuleName())) {
                    throw new UnauthorizedException("path does not match authorized scope");
                }

                if ($path->getIsFolder()) {
                    // FIXME: use more generic exceptions?
                    throw new BadRequestException("can not delete a folder");
                }
                // need to get the version before the delete
                $documentVersion = $this->remoteStorage->getVersion($path);
                $x = $this->remoteStorage->deleteDocument(
                    $path,
                    $this->stripQuotes(
                        $request->getHeader("If-Match")
                    )
                );
                $rsr = new RemoteStorageResponse($request, 200, $documentVersion, 'application/json');
                $rsr->setContent($x);

                return $rsr;
            }
        );

        $service->options(
            "*",
            function ($pathInfo) use ($request) {
                return new RemoteStorageResponse($request, 200, null, null);
            }
        );

        return $service->run();
    }

    public function hasReadScope(TokenIntrospection $i, $moduleName)
    {
        $validReadScopes = array(
            "*:r",
            "*:rw",
            sprintf("%s:%s", $moduleName, "r"),
            sprintf("%s:%s", $moduleName, "rw"),
        );

        return $i->getScope()->hasAnyScope(new Scope($validReadScopes));
    }

    public function hasWriteScope(TokenIntrospection $i, $moduleName)
    {
        $validWriteScopes = array(
            "*:rw",
            sprintf("%s:%s", $moduleName, "rw"),
        );

        return $i->getScope()->hasAnyScope(new Scope($validWriteScopes));
    }

    /**
     * ETag/If-Match/If-None-Match are always quoted, this method removes
     * the quotes
     */
    public function stripQuotes($versionHeader)
    {
        if (null === $versionHeader) {
            return null;
        }

        $versions = array();

        if ("*" === $versionHeader) {
            return array("*");
        }

        foreach (explode(",", $versionHeader) as $v) {
            $v = trim($v);
            $startQuote = strpos($v, '"');
            $endQuote = strrpos($v, '"');
            $length = strlen($v);

            if (0 !== $startQuote || $length-1 !== $endQuote) {
                throw new BadRequestException("version header must start and end with a double quote");
            }
            $versions[] = substr($v, 1, $length-2);
        }

        return $versions;
    }
}
