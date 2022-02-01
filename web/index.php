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

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\RemoteStorage\Config;
use fkooman\RemoteStorage\Controller;
use fkooman\RemoteStorage\Http\PhpSession;
use fkooman\RemoteStorage\Http\Request;

try {
    $config = Config::fromFile(sprintf('%s/config/server.yaml', $baseDir));
    $request = new Request($_SERVER, $_GET, $_POST, file_get_contents('php://input'));
    $controller = new Controller(
        $baseDir,
        $request->getRoot(),
        $config,
        new PhpSession()
    );

    $response = $controller->run($request);
    if (!$response->isOkay()) {
        error_log((string) $response);
    }
    $response->send();
} catch (Exception $e) {
    error_log($e->getMessage());

    exit(sprintf('ERROR: %s', $e->getMessage()));
}
