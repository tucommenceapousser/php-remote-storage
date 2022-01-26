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

use fkooman\RemoteStorage\Exception\ConfigException;
use Symfony\Component\Yaml\Yaml;

class Config
{
    /** @var array */
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @param string $key
     */
    public function __isset($key)
    {
        return \array_key_exists($key, $this->data);
    }

    /**
     * @param string $key
     *
     * @return array|object|string
     */
    public function __get($key)
    {
        if (!\array_key_exists($key, $this->data)) {
            // consumers MUST check first if a field is available before
            // requesting it
            throw new ConfigException(sprintf('missing field "%s" in configuration', $key));
        }

        if (\is_array($this->data[$key])) {
            // if all we get is a "flat" array with sequential numeric keys
            // return the array instead of an object
            $k = array_keys($this->data[$key]);
            if ($k === range(0, \count($k) - 1)) {
                return $this->data[$key];
            }

            return new self($this->data[$key]);
        }

        return $this->data[$key];
    }

    /**
     * @return array
     */
    public function asArray()
    {
        return $this->data;
    }

    public static function fromFile($configFile)
    {
        if (false === $fileContent = @file_get_contents($configFile)) {
            throw new ConfigException(sprintf('unable to read "%s"', $configFile));
        }

        return new static(Yaml::parse($fileContent));
    }

    public static function toFile($configFile, array $configData): void
    {
        $fileData = Yaml::dump($configData, 3);
        if (false === @file_put_contents($configFile, $fileData)) {
            throw new ConfigException(sprintf('unable to write "%s"', $configFile));
        }
    }
}
