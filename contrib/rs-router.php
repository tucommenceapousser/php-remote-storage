<?php

// You can use this router to use PHP's built in server for development
// purposes. From the main folder run this:
//
// php -S localhost:8080 -t web/ contrib/rs-router.php
//
// Now you should be able to use RS apps using 'foo@localhost:8080' as
// user address in the RS widget

//var_dump($_SERVER);

if (file_exists($_SERVER['DOCUMENT_ROOT'].$_SERVER['REQUEST_URI'])) {
    return false;
} elseif (0 === strpos($_SERVER['REQUEST_URI'], '/.well-known/webfinger')) {
    $_SERVER['SCRIPT_NAME'] = '/webfinger.php';
    require 'web/webfinger.php';
} elseif (0 === strpos($_SERVER['REQUEST_URI'], '/store')) {
    $_SERVER['SCRIPT_NAME'] = '/api.php';
    require 'web/api.php';
} else {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require 'web/index.php';
}
