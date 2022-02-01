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

class PhpSession implements SessionInterface
{
    public function __construct()
    {
        session_start(
            [
                'cookie_secure' => true,
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]
        );
    }

    public function set(string $k, string $v): void
    {
        $_SESSION[$k] = $v;
    }

    public function delete(string $k): void
    {
        unset($_SESSION[$k]);
    }

    public function get(string $k): ?string
    {
        if (!isset($_SESSION)) {
            return null;
        }

        if (!\array_key_exists($k, $_SESSION)) {
            return null;
        }

        if (!\is_string($_SESSION[$k])) {
            return null;
        }

        return $_SESSION[$k];
    }

    public function has(string $k): bool
    {
        if (!isset($_SESSION)) {
            return false;
        }

        return \array_key_exists($k, $_SESSION);
    }
}
