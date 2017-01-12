#!/usr/bin/php
<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\RemoteStorage\Config\YamlFile;

try {
    if (3 > $argc) {
        throw new Exception(
            sprintf('SYNTAX: %s [userName] [secret]', $argv[0])
        );
    }
    $userName = $argv[1];
    $secret = $argv[2];
    $passwordHash = password_hash($secret, PASSWORD_DEFAULT);

    $yamlFile = new YamlFile(
        dirname(__DIR__).'/config/server.yaml'
    );
    $configData = $yamlFile->readConfig();
    $configData['Users'][$userName] = $passwordHash;

    $yamlFile->writeConfig($configData);
} catch (Exception $e) {
    echo $e->getMessage().PHP_EOL;
    exit(1);
}
