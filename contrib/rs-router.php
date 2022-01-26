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

// purposes. From the main folder run this:
//
// php -S localhost:8080 -t web/ contrib/rs-router.php
//
// Now you should be able to use RS apps using 'foo@localhost:8080' as
// user address in the RS widget

//var_dump($_SERVER);

if (file_exists($_SERVER['DOCUMENT_ROOT'].$_SERVER['REQUEST_URI'])) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';

require 'web/index.php';
