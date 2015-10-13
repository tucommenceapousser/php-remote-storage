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
use fkooman\Rest\Plugin\Authentication\Bearer\Scope;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use fkooman\Http\Exception\NotFoundException;
use fkooman\Http\Exception\PreconditionFailedException;
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Http\Exception\BadRequestException;

class RemoteStorageService extends Service
{
    /** @var RemoteStorage */
    private $remoteStorage;

    public function __construct(RemoteStorage $remoteStorage)
    {
        parent::__construct();
        $this->remoteStorage = $remoteStorage;

        $this->addRoute(
            ['GET', 'HEAD'],
            '*',
            function (Request $request, TokenInfo $tokenInfo) {
                return $this->getObject($request, $tokenInfo);
            }
        );

        // put a document
        $this->put(
            '*',
            function (Request $request, TokenInfo $tokenInfo) {
                return $this->putDocument($request, $tokenInfo);
            }
        );

        // delete a document
        $this->delete(
            '*',
            function (Request $request, TokenInfo $tokenInfo) {
                return $this->deleteDocument($request, $tokenInfo);
            }
        );

        // options request
        $this->options(
            '*',
            function (Request $request) {
                return $this->optionsRequest($request);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array('enabled' => false),
            )
        );
    }

    public function getObject(Request $request, TokenInfo $tokenInfo)
    {
        $path = new Path($request->getUrl()->getPathInfo());

        if ($path->getIsFolder()) {
            return $this->getFolder($path, $request, $tokenInfo);
        }

        return $this->getDocument($path, $request, $tokenInfo);
    }

    public function getFolder(Path $path, Request $request, TokenInfo $tokenInfo)
    {
        if ($path->getUserId() !== $tokenInfo->getUserId()) {
            throw new ForbiddenException('path does not match authorized subject');
        }
        if (!$this->hasReadScope($tokenInfo->getScope(), $path->getModuleName())) {
            throw new ForbiddenException('path does not match authorized scope');
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
                return new RemoteStorageResponse($request, 304, $folderVersion);
            }
        }

        $rsr = new RemoteStorageResponse($request, 200, $folderVersion);
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
                throw new ForbiddenException('path does not match authorized subject');
            }
            if (!$this->hasReadScope($tokenInfo->getScope(), $path->getModuleName())) {
                throw new ForbiddenException('path does not match authorized scope');
            }
        }
        $documentVersion = $this->remoteStorage->getVersion($path);
        if (null === $documentVersion) {
            throw new NotFoundException('document not found');
        }

        $requestedVersion = $this->stripQuotes(
            $request->getHeader('If-None-Match')
        );
        $documentContentType = $this->remoteStorage->getContentType($path);

        if (null !== $requestedVersion) {
            if (in_array($documentVersion, $requestedVersion)) {
                return new RemoteStorageResponse($request, 304, $documentVersion, $documentContentType);
            }
        }

        $documentContent = $this->remoteStorage->getDocument($path);

        $rsr = new RemoteStorageResponse($request, 200, $documentVersion, $documentContentType);
        if ('GET' === $request->getMethod()) {
            $rsr->setBody(
                $this->remoteStorage->getDocument(
                    $path,
                    $requestedVersion
                )
            );
        }

        return $rsr;
    }

    public function putDocument(Request $request, TokenInfo $tokenInfo)
    {
        $path = new Path($request->getUrl()->getPathInfo());

        if ($path->getUserId() !== $tokenInfo->getUserId()) {
            throw new ForbiddenException('path does not match authorized subject');
        }
        if (!$this->hasWriteScope($tokenInfo->getScope(), $path->getModuleName())) {
            throw new ForbiddenException('path does not match authorized scope');
        }

        $ifMatch = $this->stripQuotes(
            $request->getHeader('If-Match')
        );
        $ifNoneMatch = $this->stripQuotes(
            $request->getHeader('If-None-Match')
        );

        $documentVersion = $this->remoteStorage->getVersion($path);
        if (null !== $ifMatch && !in_array($documentVersion, $ifMatch)) {
            throw new PreconditionFailedException('version mismatch');
        }

        if (null !== $ifNoneMatch && in_array('*', $ifNoneMatch) && null !== $documentVersion) {
            throw new PreconditionFailedException('document already exists');
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
        $rsr = new RemoteStorageResponse($request, 200, $documentVersion, 'application/json');
        $rsr->setBody($x);

        return $rsr;
    }

    public function deleteDocument(Request $request, TokenInfo $tokenInfo)
    {
        $path = new Path($request->getUrl()->getPathInfo());

        if ($path->getUserId() !== $tokenInfo->getUserId()) {
            throw new ForbiddenException('path does not match authorized subject');
        }
        if (!$this->hasWriteScope($tokenInfo->getScope(), $path->getModuleName())) {
            throw new ForbiddenException('path does not match authorized scope');
        }

        // need to get the version before the delete
        $documentVersion = $this->remoteStorage->getVersion($path);

        $ifMatch = $this->stripQuotes(
            $request->getHeader('If-Match')
        );

        // if document does not exist, and we have If-Match header set we should
        // return a 412 instead of a 404
        if (null !== $ifMatch && !in_array($documentVersion, $ifMatch)) {
            throw new PreconditionFailedException('version mismatch');
        }

        if (null === $documentVersion) {
            throw new NotFoundException('document not found');
        }

        $ifMatch = $this->stripQuotes(
            $request->getHeader('If-Match')
        );
        if (null !== $ifMatch && !in_array($documentVersion, $ifMatch)) {
            throw new PreconditionFailedException('version mismatch');
        }

        $x = $this->remoteStorage->deleteDocument(
            $path,
            $ifMatch
        );
        $rsr = new RemoteStorageResponse($request, 200, $documentVersion, 'application/json');
        $rsr->setBody($x);

        return $rsr;
    }

    public function optionsRequest(Request $request)
    {
        return new RemoteStorageResponse($request, 200, null, null);
    }

    private function hasReadScope(Scope $i, $moduleName)
    {
        $validReadScopes = array(
            '*:r',
            '*:rw',
            sprintf('%s:%s', $moduleName, 'r'),
            sprintf('%s:%s', $moduleName, 'rw'),
        );

        foreach ($validReadScopes as $scope) {
            if ($i->hasScope($scope)) {
                return true;
            }
        }

        return false;
    }

    private function hasWriteScope(Scope $i, $moduleName)
    {
        $validWriteScopes = array(
            '*:rw',
            sprintf('%s:%s', $moduleName, 'rw'),
        );

        foreach ($validWriteScopes as $scope) {
            if ($i->hasScope($scope)) {
                return true;
            }
        }

        return false;
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

        $versions = array();

        if ('*' === $versionHeader) {
            return array('*');
        }

        foreach (explode(',', $versionHeader) as $v) {
            $v = trim($v);
            $startQuote = strpos($v, '"');
            $endQuote = strrpos($v, '"');
            $length = strlen($v);

            if (0 !== $startQuote || $length - 1 !== $endQuote) {
                throw new BadRequestException('version header must start and end with a double quote');
            }
            $versions[] = substr($v, 1, $length - 2);
        }

        return $versions;
    }
}
