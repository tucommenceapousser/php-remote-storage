<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
$baseDir = dirname(__DIR__);

// find the autoloader (package installs, composer)
foreach (['src', 'vendor'] as $autoloadDir) {
    if (@file_exists(sprintf('%s/%s/autoload.php', $baseDir, $autoloadDir))) {
        require_once sprintf('%s/%s/autoload.php', $baseDir, $autoloadDir);
        break;
    }
}

use fkooman\RemoteStorage\Config;
use fkooman\RemoteStorage\Controller;
use fkooman\RemoteStorage\Http\Request;
use fkooman\RemoteStorage\Http\SeSession;
use fkooman\RemoteStorage\Random;
use fkooman\SeCookie\CookieOptions;
use fkooman\SeCookie\Session;

try {
    $config = Config::fromFile(sprintf('%s/config/server.yaml', $baseDir));

    $cookieOptions = CookieOptions::init();
    if ('development' === $config->serverMode) {
        $cookieOptions = $cookieOptions->withoutSecure();
    }

    $controller = new Controller(
        $baseDir,
        $config,
        new SeSession(new Session(null, $cookieOptions)),
        new Random(),
        new DateTime()
    );

    $request = new Request($_SERVER, $_GET, $_POST, file_get_contents('php://input'));
    $response = $controller->run($request);
    if (!$response->isOkay()) {
        error_log((string) $response);
    }
    $response->send();
} catch (Exception $e) {
    error_log($e->getMessage());
    exit(sprintf('ERROR: %s', $e->getMessage()));
}
