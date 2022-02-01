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

namespace fkooman\RemoteStorage\Tests;

use fkooman\RemoteStorage\Path;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class PathTest extends TestCase
{
    public function testGetFolderPath(): void
    {
        $p = new Path('/foo/bar/baz');
        static::assertSame('/foo/bar/', $p->getFolderPath());
        $p = new Path('/foo/bar/baz/');
        static::assertSame('/foo/bar/baz/', $p->getFolderPath());
    }
}
