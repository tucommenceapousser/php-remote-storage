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
    private array $serverData;
    private array $getData;
    private array $postData;
    private string $rawData;

    public function __construct(array $serverData, array $getData = [], array $postData = [], string $rawData = '')
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

    public function __toString(): string
    {
        return var_export($this->serverData, true);
    }

    public function getAuthority(): string
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
        if (!\array_key_exists('SERVER_PORT', $this->serverData)) {
            throw new HttpException('unable to determine port', 500);
        }

        return (int) $this->serverData['SERVER_PORT'];
    }

    public function getScheme(): string
    {
        if (!\array_key_exists('REQUEST_SCHEME', $this->serverData)) {
            return 'http';
        }

        return $this->serverData['REQUEST_SCHEME'];
    }

    public function getUri(): string
    {
        $requestUri = $this->serverData['REQUEST_URI'];

        return sprintf('%s%s', $this->getAuthority(), $requestUri);
    }

    public function getRoot(): string
    {
        $rootDir = \dirname($this->serverData['SCRIPT_NAME']);
        if ('/' !== $rootDir) {
            return sprintf('%s/', $rootDir);
        }

        return $rootDir;
    }

    public function getRootUri(): string
    {
        return sprintf('%s%s', $this->getAuthority(), $this->getRoot());
    }

    public function getRequestMethod(): string
    {
        return $this->serverData['REQUEST_METHOD'];
    }

    public function getServerName(): string
    {
        return $this->serverData['SERVER_NAME'];
    }

    public function isBrowser(): bool
    {
        if (!\array_key_exists('HTTP_ACCEPT', $this->serverData)) {
            return false;
        }

        return false !== mb_strpos($this->serverData['HTTP_ACCEPT'], 'text/html');
    }

    public function getPathInfo(): string
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

    /**
     * @param ?mixed $defaultValue
     *
     * @return mixed
     */
    public function getQueryParameter(string $key, bool $isRequired = true, $defaultValue = null)
    {
        return Utils::getValueFromArray($this->getData, $key, $isRequired, $defaultValue);
    }

    /**
     * @param ?mixed $defaultValue
     *
     * @return mixed
     */
    public function getPostParameter(string $key, bool $isRequired = true, $defaultValue = null)
    {
        return Utils::getValueFromArray($this->postData, $key, $isRequired, $defaultValue);
    }

    /**
     * @param ?mixed $defaultValue
     *
     * @return mixed
     */
    public function getHeader(string $key, bool $isRequired = true, $defaultValue = null)
    {
        return Utils::getValueFromArray($this->serverData, $key, $isRequired, $defaultValue);
    }

    public function getBody(): string
    {
        return $this->rawData;
    }
}
