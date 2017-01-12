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

use fkooman\RemoteStorage\Exception\PathException;
use fkooman\RemoteStorage\Http\Exception\HttpException;
use fkooman\RemoteStorage\Http\Request;
use fkooman\RemoteStorage\Http\Response;
use fkooman\RemoteStorage\Http\Service;
use fkooman\RemoteStorage\Http\ServiceModuleInterface;
use fkooman\RemoteStorage\OAuth\TokenStorage;
use InvalidArgumentException;

class ApiModule implements ServiceModuleInterface
{
    /** @var RemoteStorage */
    private $remoteStorage;

    /** @var \fkooman\RemoteStorage\OAuth\TokenStorage */
    private $tokenStorage;

    public function __construct(RemoteStorage $remoteStorage, TokenStorage $tokenStorage)
    {
        $this->remoteStorage = $remoteStorage;
        $this->tokenStorage = $tokenStorage;
    }

    public function init(Service $service)
    {
        // ApiAuth OPTIONAL??
        // maybe for "public" files it is optional?
        $service->addRoute(
            ['GET', 'HEAD'],
            '*',
            function (Request $request, TokenInfo $tokenInfo = null) {
                $response = $this->getObject($request, $tokenInfo);
                $this->addNoCache($response);
                $this->addCors($response);

                return $response;
            }
        );

        // put a document
        // ApiAuth
        $service->put(
            '*',
            function (Request $request, TokenInfo $tokenInfo) {
                $response = $this->putDocument($request, $tokenInfo);
                $this->addCors($response);

                return $response;
            }
        );

        // delete a document
        // ApiAuth
        $service->delete(
            '*',
            function (Request $request, TokenInfo $tokenInfo) {
                $response = $this->deleteDocument($request, $tokenInfo);
                $this->addCors($response);

                return $response;
            }
        );

        // options request
        // NoAuth
        $service->options(
            '*',
            function (Request $request) {
                $response = new Response();
                $response->addHeader(
                    'Access-Control-Allow-Methods',
                    'GET, PUT, DELETE, HEAD, OPTIONS'
                );
                $response->addHeader(
                    'Access-Control-Allow-Headers',
                    'Authorization, Content-Length, Content-Type, Origin, X-Requested-With, If-Match, If-None-Match'
                );
                $this->addCors($response);

                return $response;
            }
        );
    }

    public function getObject(Request $request, $tokenInfo)
    {
        $path = new Path($request->getPathInfo());

        // allow requests to public files (GET|HEAD) without authentication
        if ($path->getIsPublic() && $path->getIsDocument()) {
            return $this->getDocument($path, $request, $tokenInfo);
        }

        // past this point we MUST be authenticated
        if (null === $tokenInfo) {
            $e = new UnauthorizedException('unauthorized', 'must authenticate to view folder listing');
            $e->addScheme('Bearer', ['realm' => 'remoteStorage API']);
            throw $e;
        }

        if ($path->getIsFolder()) {
            return $this->getFolder($path, $request, $tokenInfo);
        }

        return $this->getDocument($path, $request, $tokenInfo);
    }

    public function getFolder(Path $path, Request $request, TokenInfo $tokenInfo)
    {
        if ($path->getUserId() !== $tokenInfo->getUserId()) {
            throw new HttpException('path does not match authorized subject', 403);
        }
        if (!$this->hasReadScope($tokenInfo->getScope(), $path->getModuleName())) {
            throw new HttpException('path does not match authorized scope', 403);
        }

        $folderVersion = $this->remoteStorage->getVersion($path);
        if (null === $folderVersion) {
            // folder does not exist, so we just invent this
            // ETag that will be the same for all empty folders
            $folderVersion = 'e:404';
        }

        $requestedVersion = $this->stripQuotes(
            $request->getHeader('If-None-Match')
        );

        if (null !== $requestedVersion) {
            if (in_array($folderVersion, $requestedVersion)) {
                //return new RemoteStorageResponse($request, 304, $folderVersion);
                $response = new Response(304, 'application/ld+json');
                $response->addHeader('ETag', '"'.$folderVersion.'"');

                return $response;
            }
        }

        $rsr = new Response(200, 'application/ld+json');
        $rsr->addHeader('ETag', '"'.$folderVersion.'"');

        if ('GET' === $request->getMethod()) {
            $rsr->setBody(
                $this->remoteStorage->getFolder(
                    $path,
                    $this->stripQuotes(
                        $request->getHeader('If-None-Match')
                    )
                )
            );
        }

        return $rsr;
    }

    public function getDocument(Path $path, Request $request, TokenInfo $tokenInfo = null)
    {
        if (null !== $tokenInfo) {
            if ($path->getUserId() !== $tokenInfo->getUserId()) {
                throw new HttpException('path does not match authorized subject', 403);
            }
            if (!$this->hasReadScope($tokenInfo->getScope(), $path->getModuleName())) {
                throw new HttpException('path does not match authorized scope', 403);
            }
        }
        $documentVersion = $this->remoteStorage->getVersion($path);
        if (null === $documentVersion) {
            throw new HttpException(
                sprintf('document "%s" not found', $path->getPath(), 404)
            );
        }

        $requestedVersion = $this->stripQuotes(
            $request->getHeader('If-None-Match')
        );
        $documentContentType = $this->remoteStorage->getContentType($path);

        if (null !== $requestedVersion) {
            if (in_array($documentVersion, $requestedVersion)) {
                $response = new Response(304, $documentContentType);
                $response->addHeader('ETag', '"'.$documentVersion.'"');

                return $response;
            }
        }

        $rsr = new Response(200, $documentContentType);
        $rsr->addHeader('ETag', '"'.$documentVersion.'"');

        if ('development' !== $this->options['server_mode']) {
            $rsr->addHeader('Accept-Ranges', 'bytes');
        }

        if ('GET' === $request->getMethod()) {
            if ('development' === $this->options['server_mode']) {
                // use body
                $rsr->setBody(
                    file_get_contents(
                        $this->remoteStorage->getDocument(
                            $path,
                            $requestedVersion
                        )
                    )
                );
            } else {
                // use X-SendFile
                $rsr->setFile(
                    $this->remoteStorage->getDocument(
                        $path,
                        $requestedVersion
                    )
                );
            }
        }

        return $rsr;
    }

    public function putDocument(Request $request, TokenInfo $tokenInfo)
    {
        $path = new Path($request->getPathInfo());

        if ($path->getUserId() !== $tokenInfo->getUserId()) {
            throw new HttpException('path does not match authorized subject', 403);
        }
        if (!$this->hasWriteScope($tokenInfo->getScope(), $path->getModuleName())) {
            throw new HttpException('path does not match authorized scope', 403);
        }

        $ifMatch = $this->stripQuotes(
            $request->getHeader('If-Match')
        );
        $ifNoneMatch = $this->stripQuotes(
            $request->getHeader('If-None-Match')
        );

        $documentVersion = $this->remoteStorage->getVersion($path);
        if (null !== $ifMatch && !in_array($documentVersion, $ifMatch)) {
            throw new HttpException('version mismatch', 412);
        }

        if (null !== $ifNoneMatch && in_array('*', $ifNoneMatch) && null !== $documentVersion) {
            throw new HttpException('document already exists', 412);
        }

        $x = $this->remoteStorage->putDocument(
            $path,
            $request->getHeader('Content-Type'),
            $request->getBody(),
            $ifMatch,
            $ifNoneMatch
        );
        // we have to get the version again after the PUT
        $documentVersion = $this->remoteStorage->getVersion($path);

        $rsr = new Response();
        $rsr->addHeader('ETag', '"'.$documentVersion.'"');
        $rsr->setBody($x);

        return $rsr;
    }

    public function deleteDocument(Request $request, TokenInfo $tokenInfo)
    {
        $path = new Path($request->getPathInfo());

        if ($path->getUserId() !== $tokenInfo->getUserId()) {
            throw new HttpException('path does not match authorized subject', 403);
        }
        if (!$this->hasWriteScope($tokenInfo->getScope(), $path->getModuleName())) {
            throw new HttpException('path does not match authorized scope', 403);
        }

        // need to get the version before the delete
        $documentVersion = $this->remoteStorage->getVersion($path);

        $ifMatch = $this->stripQuotes(
            $request->getHeader('If-Match')
        );

        // if document does not exist, and we have If-Match header set we should
        // return a 412 instead of a 404
        if (null !== $ifMatch && !in_array($documentVersion, $ifMatch)) {
            throw new HttpException('version mismatch', 412);
        }

        if (null === $documentVersion) {
            throw new HttpException(
                sprintf('document "%s" not found', $path->getPath()),
                404
            );
        }

        $ifMatch = $this->stripQuotes(
            $request->getHeader('If-Match')
        );
        if (null !== $ifMatch && !in_array($documentVersion, $ifMatch)) {
            throw new HttpException('version mismatch', 412);
        }

        $x = $this->remoteStorage->deleteDocument(
            $path,
            $ifMatch
        );
        $rsr = new Response();
        $rsr->addHeader('ETag', '"'.$documentVersion.'"');
        $rsr->setBody($x);

        return $rsr;
    }

    /**
     * ETag/If-Match/If-None-Match are always quoted, this method removes
     * the quotes.
     */
    public function stripQuotes($versionHeader)
    {
        if (null === $versionHeader) {
            return;
        }

        $versions = [];

        if ('*' === $versionHeader) {
            return ['*'];
        }

        foreach (explode(',', $versionHeader) as $v) {
            $v = trim($v);
            $startQuote = strpos($v, '"');
            $endQuote = strrpos($v, '"');
            $length = strlen($v);

            if (0 !== $startQuote || $length - 1 !== $endQuote) {
                throw new HttpException('version header must start and end with a double quote', 400);
            }
            $versions[] = substr($v, 1, $length - 2);
        }

        return $versions;
    }

//    public function run(Request $request = null)
//    {
//        if (null === $request) {
//            throw new InvalidArgumentException('must provide Request object');
//        }

//        $response = null;
//        try {
//            $response = parent::run($request);
//        } catch (PathException $e) {
//            $e = new BadRequestException($e->getMessage());
//            $response = $e->getJsonResponse();
//        }

//        // if error, add CORS
//        $statusCode = $response->getStatusCode();
//        if (400 <= $statusCode && 500 > $statusCode) {
//            $this->addCors($response);
//            $this->addNoCache($response);
//        }

//        return $response;
//    }

    private function hasReadScope(Scope $i, $moduleName)
    {
        $validReadScopes = [
            '*:r',
            '*:rw',
            sprintf('%s:%s', $moduleName, 'r'),
            sprintf('%s:%s', $moduleName, 'rw'),
        ];

        foreach ($validReadScopes as $scope) {
            if ($i->hasScope($scope)) {
                return true;
            }
        }

        return false;
    }

    private function hasWriteScope(Scope $i, $moduleName)
    {
        $validWriteScopes = [
            '*:rw',
            sprintf('%s:%s', $moduleName, 'rw'),
        ];

        foreach ($validWriteScopes as $scope) {
            if ($i->hasScope($scope)) {
                return true;
            }
        }

        return false;
    }

//    private function addCors(Response &$response)
//    {
//        $response->addHeader('Access-Control-Allow-Origin', '*');
//        $response->addHeader(
//            'Access-Control-Expose-Headers',
//            'ETag, Content-Length'
//        );
//    }

//    private function addNoCache(Response &$response)
//    {
//        $response->addHeader('Expires', '0');
//        $response->addHeader('Cache-Control', 'no-cache');
//    }
}
