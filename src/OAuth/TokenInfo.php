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
    /** @var array{user_id:string,scope:string} */
    private array $tokenInfo;

    /**
     * XXX we MUST be sure this is actually true in caller...
     *
     * @param array{user_id:string,scope:string} $tokenInfo
     */
    public function __construct(array $tokenInfo)
    {
        $this->tokenInfo = $tokenInfo;
    }

    public function getUserId(): string
    {
        return $this->tokenInfo['user_id'];
    }

    public function getScope(): string
    {
        return $this->tokenInfo['scope'];
    }
}
