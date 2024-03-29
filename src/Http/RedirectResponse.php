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

class RedirectResponse extends Response
{
    public function __construct(string $redirectUri, int $statusCode = 302)
    {
        parent::__construct($statusCode);
        $this->addHeader('Location', $redirectUri);
    }
}
