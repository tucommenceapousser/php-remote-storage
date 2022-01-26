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
use fkooman\RemoteStorage\OAuth\TokenInfo;

class ApiModule
{
    /** @var RemoteStorage */
    private $remoteStorage;

    /** @var string */
    private $serverMode;

    public function __construct(RemoteStorage $remoteStorage, $serverMode)
    {
        $this->remoteStorage = $remoteStorage;
        $this->serverMode = $serverMode;
    }

    /**
     * @param false|\fkooman\RemoteStorage\OAuth\TokenInfo $tokenInfo
     */
    public function get(Request $request, $tokenInfo)
    {
        $response = $this->getObject($request, $tokenInfo);
        $this->addNoCache($response);
        $this->addCors($response);

        return $response;
    }

    public function head(Request $request, $tokenInfo)
    {
        // XXX return headers only?
        $response = $this->getObject($request, $tokenInfo);
        $this->addNoCache($response);
        $this->addCors($response);

        return $response;
    }

    public function put(Request $request, TokenInfo $tokenInfo)
    {
        $response = $this->putDocument($request, $tokenInfo);
        $this->addCors($response);

        return $response;
    }

    public function delete(Request $request, TokenInfo $tokenInfo)
    {
        $response = $this->deleteDocument($request, $tokenInfo);
        $this->addCors($response);

        return $response;
    }

    public function options(Request $request)
    {
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

    /**
     * @param false|TokenInfo $tokenInfo
     */
    public function getObject(Request $request, $tokenInfo)
    {
        $path = new Path($request->getPathInfo());

        // allow requests to public files (GET|HEAD) without authentication
        if ($path->getIsPublic() && $path->getIsDocument()) {
            // XXX create a getPublicDocument call instead to make sure?
            return $this->getDocument($path, $request, $tokenInfo);
        }

        // past this point we MUST be authenticated
        if (false === $tokenInfo) {
            throw new HttpException('no_token', 401, ['WWW-Authenticate' => 'Bearer realm="remoteStorage API"']);
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
            $request->getHeader('HTTP_IF_NONE_MATCH', false, null)
        );

        if (null !== $requestedVersion) {
            if (\in_array($folderVersion, $requestedVersion, true)) {
                //return new RemoteStorageResponse($request, 304, $folderVersion);
                $response = new Response(304, 'application/ld+json');
                $response->addHeader('ETag', '"'.$folderVersion.'"');

                return $response;
            }
        }

        $rsr = new Response(200, 'application/ld+json');
        $rsr->addHeader('ETag', '"'.$folderVersion.'"');

        if ('GET' === $request->getRequestMethod()) {
            $rsr->setBody(
                $this->remoteStorage->getFolder(
                    $path,
                    $this->stripQuotes(
                        $request->getHeader('HTTP_IF_NONE_MATCH', false, null)
                    )
                )
            );
        }

        return $rsr;
    }

    public function getDocument(Path $path, Request $request, $tokenInfo)
    {
        if (false !== $tokenInfo) {
            if ($path->getUserId() !== $tokenInfo->getUserId()) {
                throw new HttpException('path does not match authorized subject', 403);
            }
            if (!$this->hasReadScope($tokenInfo->getScope(), $path->getModuleName())) {
                throw new HttpException('path does not match authorized scope', 403);
            }
        }
        $documentVersion = $this->remoteStorage->getVersion($path);
        if (null === $documentVersion) {
            throw new HttpException(sprintf('document "%s" not found', $path->getPath()), 404);
        }

        $requestedVersion = $this->stripQuotes(
            $request->getHeader('HTTP_IF_NONE_MATCH', false, null)
        );
        $documentContentType = $this->remoteStorage->getContentType($path);

        if (null !== $requestedVersion) {
            if (\in_array($documentVersion, $requestedVersion, true)) {
                $response = new Response(304, $documentContentType);
                $response->addHeader('ETag', '"'.$documentVersion.'"');

                return $response;
            }
        }

        $rsr = new Response(200, $documentContentType);
        $rsr->addHeader('ETag', '"'.$documentVersion.'"');

        if ('development' !== $this->serverMode) {
            $rsr->addHeader('Accept-Ranges', 'bytes');
        }

        if ('GET' === $request->getRequestMethod()) {
            if ('development' === $this->serverMode) {
                // use body
                $rsr->setBody(
                    file_get_contents(
                        $this->remoteStorage->getDocument(
                            $path,
                            $requestedVersion
                        )
                    )
                );
                $rsr->addHeader('Content-Length', (string) \strlen($rsr->getBody()));
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

        // https://tools.ietf.org/html/rfc7231#section-4.3.4
        if (null !== $request->getHeader('HTTP_CONTENT_RANGE', false, null)) {
            throw new HttpException('PUT MUST NOT have Content-Range', 400);
        }

        $ifMatch = $this->stripQuotes(
            $request->getHeader('HTTP_IF_MATCH', false, null)
        );
        $ifNoneMatch = $this->stripQuotes(
            $request->getHeader('HTTP_IF_NONE_MATCH', false, null)
        );

        $documentVersion = $this->remoteStorage->getVersion($path);
        if (null !== $ifMatch && !\in_array($documentVersion, $ifMatch, true)) {
            throw new HttpException('version mismatch', 412);
        }

        if (null !== $ifNoneMatch && \in_array('*', $ifNoneMatch, true) && null !== $documentVersion) {
            throw new HttpException('document already exists', 412);
        }

        $this->remoteStorage->putDocument(
            $path,
            $request->getHeader('CONTENT_TYPE'),
            $request->getBody(),
            $ifMatch,
            $ifNoneMatch
        );
        // we have to get the version again after the PUT
        $documentVersion = $this->remoteStorage->getVersion($path);

        $rsr = new Response();
        $rsr->addHeader('ETag', '"'.$documentVersion.'"');
        $rsr->setBody('');

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
            $request->getHeader('HTTP_IF_MATCH', false, null)
        );

        // if document does not exist, and we have If-Match header set we should
        // return a 412 instead of a 404
        if (null !== $ifMatch && !\in_array($documentVersion, $ifMatch, true)) {
            throw new HttpException('version mismatch', 412);
        }

        if (null === $documentVersion) {
            throw new HttpException(sprintf('document "%s" not found', $path->getPath()), 404);
        }

        $ifMatch = $this->stripQuotes(
            $request->getHeader('HTTP_IF_MATCH', false, null)
        );
        if (null !== $ifMatch && !\in_array($documentVersion, $ifMatch, true)) {
            throw new HttpException('version mismatch', 412);
        }

        $this->remoteStorage->deleteDocument(
            $path,
            $ifMatch
        );
        $rsr = new Response();
        $rsr->addHeader('ETag', '"'.$documentVersion.'"');
        $rsr->setBody('');

        return $rsr;
    }

    /**
     * ETag/If-Match/If-None-Match are always quoted, this method removes
     * the quotes.
     *
     * @param mixed $versionHeader
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
            $length = \strlen($v);

            if (0 !== $startQuote || $length - 1 !== $endQuote) {
                throw new HttpException('version header must start and end with a double quote', 400);
            }
            $versions[] = substr($v, 1, $length - 2);
        }

        return $versions;
    }

    private function hasReadScope($scope, $moduleName)
    {
        $obtainedScopes = explode(' ', $scope);
        $requiredScopes = [
            '*:r',
            '*:rw',
            sprintf('%s:%s', $moduleName, 'r'),
            sprintf('%s:%s', $moduleName, 'rw'),
        ];

        foreach ($requiredScopes as $requiredScope) {
            if (\in_array($requiredScope, $obtainedScopes, true)) {
                return true;
            }
        }

        return false;
    }

    private function hasWriteScope($scope, $moduleName)
    {
        $obtainedScopes = explode(' ', $scope);
        $requiredScopes = [
            '*:rw',
            sprintf('%s:%s', $moduleName, 'rw'),
        ];

        foreach ($requiredScopes as $requiredScope) {
            if (\in_array($requiredScope, $obtainedScopes, true)) {
                return true;
            }
        }

        return false;
    }

    private function addCors(Response &$response): void
    {
        $response->addHeader('Access-Control-Allow-Origin', '*');
        $response->addHeader(
            'Access-Control-Expose-Headers',
            'ETag, Content-Length'
        );
    }

    private function addNoCache(Response &$response): void
    {
        $response->addHeader('Expires', '0');
        $response->addHeader('Cache-Control', 'no-cache');
    }
}
