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
    private int $statusCode;

    /** @var array<string,string> */
    private array $headers;
    private string $body;

    public function __construct(int $statusCode = 200, string $contentType = 'text/plain')
    {
        $this->statusCode = $statusCode;
        $this->headers = [
            'Content-Type' => $contentType,
        ];
        $this->body = '';
    }

    public function __toString(): string
    {
        $output = $this->statusCode.PHP_EOL;
        foreach ($this->headers as $k => $v) {
            $output .= sprintf('%s: %s', $k, $v).PHP_EOL;
        }
        $output .= PHP_EOL;
        $output .= $this->body;

        return $output;
    }

    public function isOkay(): bool
    {
        return 200 <= $this->statusCode && 300 > $this->statusCode;
    }

    public function addHeader(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }

    public function getHeader($key): ?string
    {
        if (\array_key_exists($key, $this->headers)) {
            return $this->headers[$key];
        }

        return null;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function toArray(): array
    {
        $output = [$this->statusCode];
        foreach ($this->headers as $key => $value) {
            $output[] = sprintf('%s: %s', $key, $value);
        }
        $output[] = '';
        $output[] = $this->body;

        return $output;
    }

    public function setFile(string $fileName): void
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
