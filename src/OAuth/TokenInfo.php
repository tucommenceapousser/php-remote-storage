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

namespace fkooman\RemoteStorage\OAuth;

class TokenInfo
{
    /** @var array */
    private $tokenInfo;

    public function __construct(array $tokenInfo)
    {
        $this->tokenInfo = $tokenInfo;
    }

    public function getUserId()
    {
        return $this->tokenInfo['user_id'];
    }

    public function getScope()
    {
        return $this->tokenInfo['scope'];
    }
}
