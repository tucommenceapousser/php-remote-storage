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
require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\RemoteStorage\Config;
use fkooman\RemoteStorage\Http\Request;
use fkooman\RemoteStorage\Http\Service;
use fkooman\RemoteStorage\WebfingerModule;

try {
    $config = Config::fromFile(dirname(__DIR__).'/config/server.yaml');
    $serverMode = $config->getItem('serverMode');

    $request = new Request($_SERVER, $_GET, $_POST);
    $service = new Service();
    $service->addModule(new WebfingerModule($serverMode));
    $service->run($request)->send();
} catch (Exception $e) {
    error_log($e->getMessage());
    die(sprintf('ERROR: %s', $e->getMessage()));
}
