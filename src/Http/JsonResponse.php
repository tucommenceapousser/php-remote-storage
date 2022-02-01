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

class JsonResponse extends Response
{
    public function __construct(array $responseData, int $responseCode = 200)
    {
        parent::__construct($responseCode, 'application/json');
        $this->setBody(json_encode($responseData));
    }
}
