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

use fkooman\RemoteStorage\Http\Exception\HttpException;

class Utils
{
    /**
     * @param mixed $defaultValue
     *
     * @return mixed
     */
    public static function getValueFromArray(array $sourceData, string $key, bool $isRequired, $defaultValue)
    {
        if (\array_key_exists($key, $sourceData)) {
            return $sourceData[$key];
        }

        if ($isRequired) {
            throw new HttpException(sprintf('missing required field "%s"', $key), 400);
        }

        return $defaultValue;
    }
}
