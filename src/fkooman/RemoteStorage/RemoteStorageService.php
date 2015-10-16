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
use fkooman\OAuth\OAuthServer;
use fkooman\OAuth\OAuthService;
use fkooman\Rest\Plugin\Authentication\Bearer\Scope;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use fkooman\Http\Exception\NotFoundException;
use fkooman\Http\Exception\PreconditionFailedException;
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Http\Exception\BadRequestException;
use fkooman\Http\Exception\UnauthorizedException;
use fkooman\Rest\Plugin\Authentication\AuthenticationPluginInterface;
use fkooman\Http\Response;
use InvalidArgumentException;
use fkooman\RemoteStorage\Exception\PathException;

class RemoteStorageService extends OAuthService
{
    /** @var RemoteStorage */
    private $remoteStorage;

    public function __construct(OAuthServer $server, RemoteStorage $remoteStorage, AuthenticationPluginInterface $userAuth, AuthenticationPluginInterface $apiAuth, array $opt = array())
    {
        parent::__construct($server, $userAuth, $apiAuth, $opt);
        $this->remoteStorage = $remoteStorage;

        $this->addRoute(
            ['GET', 'HEAD'],
            '*',
            function (Request $request, TokenInfo $tokenInfo = null) {
                return $this->getObject($request, $tokenInfo);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'activate' => array('api'),
                    'require' => false,
                ),
            )
        );

        // put a document
        $this->put(
            '*',
            function (Request $request, TokenInfo $tokenInfo) {
                return $this->putDocument($request, $tokenInfo);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'activate' => array('api'),
                ),
            )
        );

        // delete a document
        $this->delete(
            '*',
            function (Request $request, TokenInfo $tokenInfo) {
                return $this->deleteDocument($request, $tokenInfo);
            },
            array(
                'fkooman\Rest\Plugin\Authentication\AuthenticationPlugin' => array(
                    'activate' => array('api'),
                ),
            )

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

    public function getObject(Request $request, $tokenInfo)
    {
        $path = new Path($request->getUrl()->getPathInfo());

        // allow requests to public files (GET|HEAD) without authentication
        if ($path->getIsPublic() && $path->getIsDocument()) {
            return $this->getDocument($path, $request, $tokenInfo);
        }

        // past this point we MUST be authenticated
        if (null === $tokenInfo) {
            $e = new UnauthorizedException('unauthorized', 'must authenticate to view folder listing');
            $e->addScheme('Bearer', array('realm' => 'remoteStorage API'));
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
                //return new RemoteStorageResponse($request, 304, $folderVersion);
                $response = new Response(304, 'application/ld+json');
                $response->setHeader('ETag', '"'.$folderVersion.'"');

                return $response;
            }
        }

#        $rsr = new RemoteStorageResponse($request, 200, $folderVersion);
        $rsr = new Response(200, 'application/ld+json');
        $rsr->setHeader('ETag', '"'.$folderVersion.'"');

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
                //return new RemoteStorageResponse($request, 304, $documentVersion, $documentContentType);

                $response = new Response(304, $documentContentType);
                $response->setHeader('ETag', '"'.$documentVersion.'"');

                return $response;
            }
        }

        // $rsr = new RemoteStorageResponse($request, 200, $documentVersion, $documentContentType);
        $rsr = new Response(200, $documentContentType);
        $rsr->setHeader('ETag', '"'.$documentVersion.'"');

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

        //$rsr = new RemoteStorageResponse($request, 200, $documentVersion, 'application/json');
        $rsr = new Response();
        $rsr->setHeader('ETag', '"'.$documentVersion.'"');
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
        //$rsr = new RemoteStorageResponse($request, 200, $documentVersion, 'application/json');
        $rsr = new Response();
        $rsr->setHeader('ETag', '"'.$documentVersion.'"');
        $rsr->setBody($x);

        return $rsr;
    }

    public function optionsRequest(Request $request)
    {
        //return new RemoteStorageResponse($request, 200, null, null);
        return new Response();
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

    public function run(Request $request = null)
    {
        if (null === $request) {
            throw new InvalidArgumentException('must provide Request object');
        }

        $response = null;
        try {
            $response = parent::run($request);
        } catch (PathException $e) {
            $e = new BadRequestException($e->getMessage());
            $response = $e->getJsonResponse();
        }

        if ('GET' === $request->getMethod()) {
            $response->setHeader('Expires', 0);
        }

        // CORS
        if (null !== $request->getHeader('Origin')) {
            $response->setHeader('Access-Control-Allow-Origin', $request->getHeader('Origin'));
        } elseif (in_array($request->getMethod(), array('GET', 'HEAD', 'OPTIONS'))) {
            $response->setHeader('Access-Control-Allow-Origin', '"*"');
        }

        $response->setHeader(
            'Access-Control-Expose-Headers',
            'ETag, Content-Length'
        );

        // this is only needed for OPTIONS requests
        if ('OPTIONS' === $request->getMethod()) {
            $response->setHeader(
                'Access-Control-Allow-Methods',
                'GET, PUT, DELETE, HEAD, OPTIONS'
            );
            // FIXME: are Origin and X-Requested-With really needed?
            $response->setHeader(
                'Access-Control-Allow-Headers',
                'Authorization, Content-Length, Content-Type, Origin, X-Requested-With, If-Match, If-None-Match'
            );
        }

        return $response;
    }
}
