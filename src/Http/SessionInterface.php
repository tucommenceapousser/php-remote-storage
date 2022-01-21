<?php

namespace fkooman\RemoteStorage\Http;

interface SessionInterface
{
    public function set(string $k, string $v): void;

    public function delete(string $k): void;

    public function get(string $k): ?string;

    public function has(string $k): bool;
}
