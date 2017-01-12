<?php

/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\RemoteStorage\Config;

use RuntimeException;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Yaml;

class YamlFile implements ReaderInterface, WriterInterface
{
    /** @var array */
    private $configFile;

    public function __construct($configFile)
    {
        if (!is_array($configFile)) {
            $configFile = [$configFile];
        }

        $this->configFile = $configFile;
    }

    public function readConfig()
    {
        foreach ($this->configFile as $configFile) {
            $fileContent = @file_get_contents($configFile);
            if (false !== $fileContent) {
                return Yaml::parse($fileContent);
            }
        }

        throw new RuntimeException(sprintf('unable to read configuration file(s) "%s"', implode(',', $this->configFile)));
    }

    public function writeConfig(array $config)
    {
        $dumper = new Dumper();
        $yamlStr = $dumper->dump($config, 3);
        foreach ($this->configFile as $configFile) {
            if (false !== @file_put_contents($configFile, $yamlStr)) {
                return;
            }
        }

        throw new RuntimeException(sprintf('unable to write configuration file(s) "%s"', implode(',', $this->configFile)));
    }
}
