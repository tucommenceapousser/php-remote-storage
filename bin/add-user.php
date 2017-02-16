#!/usr/bin/php
<?php

require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use fkooman\RemoteStorage\Config;

try {
    if (3 > $argc) {
        throw new Exception(
            sprintf('SYNTAX: %s [userName] [secret]', $argv[0])
        );
    }
    $userName = $argv[1];
    $secret = $argv[2];
    $passwordHash = password_hash($secret, PASSWORD_DEFAULT);

    $config = Config::fromFile(dirname(__DIR__).'/config/server.yaml');
    $configData = $config->asArray();
    $configData['Users'][$userName] = $passwordHash;

    Config::toFile(dirname(__DIR__).'/config/server.yaml', $configData);
} catch (Exception $e) {
    echo $e->getMessage().PHP_EOL;
    exit(1);
}
