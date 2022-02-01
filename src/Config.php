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
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function productionMode(): bool
    {
        if (!\array_key_exists('serverMode', $this->data)) {
            return false;
        }

        return 'production' === $this->data['serverMode'];
    }

    /**
     * @return array<string,string>
     */
    public function userList(): array
    {
        if (!\array_key_exists('Users', $this->data)) {
            return [];
        }

        if (!\is_array($this->data['Users'])) {
            return [];
        }

        return $this->data['Users'];
    }

    public function asArray(): array
    {
        return $this->data;
    }

    public static function fromFile(string $configFile): self
    {
        if (false === $fileContent = file_get_contents($configFile)) {
            throw new ConfigException(sprintf('unable to read "%s"', $configFile));
        }

        return new self(Yaml::parse($fileContent));
    }

    public static function toFile(string $configFile, array $configData): void
    {
        $fileData = Yaml::dump($configData, 3);
        if (false === file_put_contents($configFile, $fileData)) {
            throw new ConfigException(sprintf('unable to write "%s"', $configFile));
        }
    }
}
