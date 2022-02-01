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
    private RemoteStorage $remoteStorage;
    private bool $productionMode;

    public function __construct(RemoteStorage $remoteStorage, bool $productionMode)
    {
        $this->remoteStorage = $remoteStorage;
        $this->productionMode = $productionMode;
    }

    public function get(Request $request, ?TokenInfo $tokenInfo): Response
    {
        $response = $this->getObject($request, $tokenInfo);
        $this->addNoCache($response);
        $this->addCors($response);

        return $response;
    }

    public function head(Request $request, ?TokenInfo $tokenInfo): Response
    {
        // XXX return headers only?
        $response = $this->getObject($request, $tokenInfo);
        $this->addNoCache($response);
        $this->addCors($response);

        return $response;
    }

    public function put(Request $request, TokenInfo $tokenInfo): Response
    {
        $response = $this->putDocument($request, $tokenInfo);
        $this->addCors($response);

        return $response;
    }

    public function delete(Request $request, TokenInfo $tokenInfo): Response
    {
        $response = $this->deleteDocument($request, $tokenInfo);
        $this->addCors($response);

        return $response;
    }

    public function options(Request $request): Response
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

    public function getObject(Request $request, ?TokenInfo $tokenInfo): Response
    {
        $path = new Path($request->getPathInfo());

        // allow requests to public files (GET|HEAD) without authentication
        if ($path->getIsPublic() && $path->getIsDocument()) {
            // XXX create a getPublicDocument call instead to make sure?
            return $this->getDocument($path, $request, $tokenInfo);
        }

        // past this point we MUST be authenticated
        if (null === $tokenInfo) {
            throw new HttpException('no_token', 401, ['WWW-Authenticate' => 'Bearer realm="remoteStorage API"']);
        }

        if ($path->getIsFolder()) {
            return $this->getFolder($path, $request, $tokenInfo);
        }

        return $this->getDocument($path, $request, $tokenInfo);
    }

    public function getFolder(Path $path, Request $request, TokenInfo $tokenInfo): Response
    {
        if ($path->getUserId() !== $tokenInfo->getUserId()) {
            throw new HttpException('path does not match authorized subject', 403);
        }
        if (null === $moduleName = $path->getModuleName()) {
            throw new HttpException('path does not have module name', 403);
        }
        if (!$this->hasReadScope($tokenInfo->getScope(), $moduleName)) {
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

    public function getDocument(Path $path, Request $request, ?TokenInfo $tokenInfo): Response
    {
        if (null !== $tokenInfo) {
            if ($path->getUserId() !== $tokenInfo->getUserId()) {
                throw new HttpException('path does not match authorized subject', 403);
            }
            if (null === $moduleName = $path->getModuleName()) {
                throw new HttpException('path does not have module name', 403);
            }
            if (!$this->hasReadScope($tokenInfo->getScope(), $moduleName)) {
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

        if ($this->productionMode) {
            $rsr->addHeader('Accept-Ranges', 'bytes');
        }

        if ('GET' === $request->getRequestMethod()) {
            if (!$this->productionMode) {
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

    public function putDocument(Request $request, TokenInfo $tokenInfo): Response
    {
        $path = new Path($request->getPathInfo());

        if ($path->getUserId() !== $tokenInfo->getUserId()) {
            throw new HttpException('path does not match authorized subject', 403);
        }
        if (null === $moduleName = $path->getModuleName()) {
            throw new HttpException('path does not have module name', 403);
        }
        if (!$this->hasWriteScope($tokenInfo->getScope(), $moduleName)) {
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

    public function deleteDocument(Request $request, TokenInfo $tokenInfo): Response
    {
        $path = new Path($request->getPathInfo());

        if ($path->getUserId() !== $tokenInfo->getUserId()) {
            throw new HttpException('path does not match authorized subject', 403);
        }
        if (null === $moduleName = $path->getModuleName()) {
            throw new HttpException('path does not have module name', 403);
        }
        if (!$this->hasWriteScope($tokenInfo->getScope(), $moduleName)) {
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
     * @return ?array<string>
     */
    public function stripQuotes(?string $versionHeader): ?array
    {
        if (null === $versionHeader) {
            return null;
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

    private function hasReadScope(string $scope, string $moduleName): bool
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

    private function hasWriteScope(string $scope, string $moduleName): bool
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
