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

interface SessionInterface
{
    public function set(string $k, string $v): void;

    public function delete(string $k): void;

    public function get(string $k): ?string;

    public function has(string $k): bool;
}
