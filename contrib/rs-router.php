<?php

// You can use this router to use PHP's built in server for development
// purposes. From the main folder run this:
//
// php -S localhost:8080 -t web/ contrib/rs-router.php
//
// Now you should be able to use RS apps using 'foo@localhost:8080' as
// user address in the RS widget

if (file_exists($_SERVER['DOCUMENT_ROOT'].$_SERVER['REQUEST_URI'])) {
    return false;
} else {
    $questionPos = strpos($_SERVER['REQUEST_URI'], '?');
    if (false !== $questionPos) {
        $_SERVER['PATH_INFO'] = substr($_SERVER['REQUEST_URI'], 0, $questionPos);
    } else {
        $_SERVER['PATH_INFO'] = $_SERVER['REQUEST_URI'];
    }

    require 'web/index.php';
}
