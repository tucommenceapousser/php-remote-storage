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
    /** @var array */
    private $responseHeaders;

    public function __construct($message, $code, array $responseHeaders = [], Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->responseHeaders = $responseHeaders;
    }

    public function getResponseHeaders()
    {
        return $this->responseHeaders;
    }
}
