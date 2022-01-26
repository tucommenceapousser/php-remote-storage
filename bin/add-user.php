#!/usr/bin/php
<?php

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\RemoteStorage\Config;

try {
    if (3 > $argc) {
        throw new Exception(sprintf('SYNTAX: %s [userName] [secret]', $argv[0]));
    }
    $userName = $argv[1];
    $secret = $argv[2];
    $passwordHash = password_hash($secret, \PASSWORD_DEFAULT);

    $config = Config::fromFile(sprintf('%s/config/server.yaml', $baseDir));
    $configData = $config->asArray();
    $configData['Users'][$userName] = $passwordHash;

    Config::toFile(sprintf('%s/config/server.yaml', $baseDir), $configData);
} catch (Exception $e) {
    echo $e->getMessage().\PHP_EOL;

    exit(1);
}
