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

class Response
{
    /** @var int */
    private $statusCode;

    /** @var array */
    private $headers;

    /** @var string */
    private $body;

    public function __construct($statusCode = 200, $contentType = 'text/plain')
    {
        $this->statusCode = $statusCode;
        $this->headers = [
            'Content-Type' => $contentType,
        ];
        $this->body = '';
    }

    public function __toString()
    {
        $output = $this->statusCode.PHP_EOL;
        foreach ($this->headers as $k => $v) {
            $output .= sprintf('%s: %s', $k, $v).PHP_EOL;
        }
        $output .= PHP_EOL;
        $output .= $this->body;

        return $output;
    }

    public function isOkay()
    {
        return 200 <= $this->statusCode && 300 > $this->statusCode;
    }

    public function addHeader($key, $value): void
    {
        $this->headers[$key] = $value;
    }

    public function getHeader($key)
    {
        if (\array_key_exists($key, $this->headers)) {
            return $this->headers[$key];
        }
    }

    public function setBody($body): void
    {
        $this->body = $body;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function toArray()
    {
        $output = [$this->statusCode];
        foreach ($this->headers as $key => $value) {
            $output[] = sprintf('%s: %s', $key, $value);
        }
        $output[] = '';
        $output[] = $this->body;

        return $output;
    }

    public function setFile($fileName): void
    {
        $this->addHeader('X-SENDFILE', $fileName);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $key => $value) {
            header(sprintf('%s: %s', $key, $value));
        }

        echo $this->body;
    }
}
