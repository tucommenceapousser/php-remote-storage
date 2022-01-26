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

use fkooman\SeCookie\Session;

class SeSession implements SessionInterface
{
    private Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
        $this->session->start();
    }

    public function set(string $k, string $v): void
    {
        $this->session->set($k, $v);
    }

    public function delete(string $k): void
    {
        $this->session->remove($k);
    }

    public function get(string $k): ?string
    {
        return $this->session->get($k);
    }

    public function has(string $k): bool
    {
        return null !== $this->session->get($k);
    }
}
