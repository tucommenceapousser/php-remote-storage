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

namespace fkooman\RemoteStorage;

class Random implements RandomInterface
{
    public function get(int $length): string
    {
        return sodium_bin2hex(
            random_bytes($length)
        );
    }
}
