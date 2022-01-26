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

namespace fkooman\RemoteStorage\Http;

use fkooman\RemoteStorage\Http\Exception\HttpException;

class Request
{
    /** @var array */
    private $serverData;

    /** @var array */
    private $getData;

    /** @var array */
    private $postData;

    /** @var null|string */
    private $rawData;

    public function __construct(array $serverData, array $getData = [], array $postData = [], $rawData = null)
    {
        $requiredHeaders = [
            'REQUEST_METHOD',
            'SERVER_NAME',
            'SERVER_PORT',
            'REQUEST_URI',
            'SCRIPT_NAME',
        ];

        foreach ($requiredHeaders as $key) {
            if (!\array_key_exists($key, $serverData)) {
                // this indicates something wrong with the interaction between
                // the web server and PHP, these headers MUST always be available
                throw new HttpException(sprintf('missing header "%s"', $key), 500);
            }
        }
        $this->serverData = $serverData;
        $this->getData = $getData;
        $this->postData = $postData;
        $this->rawData = $rawData;
    }

    public function __toString()
    {
        return var_export($this->serverData, true);
    }

    public function getAuthority()
    {
        // server_name
        $serverName = $this->serverData['SERVER_NAME'];

        $usePort = false;
        if ('https' === $this->getScheme() && 443 !== $this->getPort()) {
            $usePort = true;
        }
        if ('http' === $this->getScheme() && 80 !== $this->getPort()) {
            $usePort = true;
        }

        if ($usePort) {
            return sprintf('%s://%s:%d', $this->getScheme(), $serverName, $this->getPort());
        }

        return sprintf('%s://%s', $this->getScheme(), $serverName);
    }

    public function getPort(): int
    {
        return $this->serverData['SERVER_PORT'];
    }

    public function getScheme(): string
    {
        if (!\array_key_exists('REQUEST_SCHEME', $this->serverData)) {
            return 'http';
        }

        return $this->serverData['REQUEST_SCHEME'];
    }

    public function getUri()
    {
        $requestUri = $this->serverData['REQUEST_URI'];

        return sprintf('%s%s', $this->getAuthority(), $requestUri);
    }

    public function getRoot()
    {
        $rootDir = \dirname($this->serverData['SCRIPT_NAME']);
        if ('/' !== $rootDir) {
            return sprintf('%s/', $rootDir);
        }

        return $rootDir;
    }

    public function getRootUri()
    {
        return sprintf('%s%s', $this->getAuthority(), $this->getRoot());
    }

    public function getRequestMethod()
    {
        return $this->serverData['REQUEST_METHOD'];
    }

    public function getServerName()
    {
        return $this->serverData['SERVER_NAME'];
    }

    public function isBrowser()
    {
        if (!\array_key_exists('HTTP_ACCEPT', $this->serverData)) {
            return false;
        }

        return false !== mb_strpos($this->serverData['HTTP_ACCEPT'], 'text/html');
    }

    public function getPathInfo()
    {
        // remove the query string
        $requestUri = $this->serverData['REQUEST_URI'];
        if (false !== $pos = mb_strpos($requestUri, '?')) {
            $requestUri = mb_substr($requestUri, 0, $pos);
        }

        // remove script_name (if it is part of request_uri
        if (0 === mb_strpos($requestUri, $this->serverData['SCRIPT_NAME'])) {
            return substr($requestUri, mb_strlen($this->serverData['SCRIPT_NAME']));
        }

        // remove the root
        if ('/' !== $this->getRoot()) {
            return mb_substr($requestUri, mb_strlen($this->getRoot()) - 1);
        }

        return $requestUri;
    }

    public function getQueryParameter($key, $isRequired = true, $defaultValue = null)
    {
        return Utils::getValueFromArray($this->getData, $key, $isRequired, $defaultValue);
    }

    public function getPostParameter($key, $isRequired = true, $defaultValue = null)
    {
        return Utils::getValueFromArray($this->postData, $key, $isRequired, $defaultValue);
    }

    public function getHeader($key, $isRequired = true, $defaultValue = null)
    {
        return Utils::getValueFromArray($this->serverData, $key, $isRequired, $defaultValue);
    }

    public function getBody()
    {
        return $this->rawData;
    }
}
