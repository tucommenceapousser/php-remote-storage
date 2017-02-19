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
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use fkooman\RemoteStorage\Controller;
use fkooman\RemoteStorage\Http\Request;
use fkooman\RemoteStorage\Http\Session;
use fkooman\RemoteStorage\Random;

try {
    $appDir = dirname(__DIR__);
    $controller = new Controller(
        $appDir,
        new Session(),
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
    die(sprintf('ERROR: %s', $e->getMessage()));
}
