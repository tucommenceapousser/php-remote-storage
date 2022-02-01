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

namespace fkooman\RemoteStorage\Http\Exception;

use Exception;

class HttpException extends Exception
{
    /** @var array<string,string> */
    private array $responseHeaders;

    /**
     * @param array<string,string> $responseHeaders
     */
    public function __construct(string $message, int $code, array $responseHeaders = [], Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->responseHeaders = $responseHeaders;
    }

    /**
     * @return array<string,string>
     */
    public function getResponseHeaders(): array
    {
        return $this->responseHeaders;
    }
}
