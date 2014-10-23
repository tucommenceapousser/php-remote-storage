<?php
$vendorDir = '/usr/share/php';
$baseDir   = dirname(__DIR__);

require_once $vendorDir.'/Symfony/Component/ClassLoader/UniversalClassLoader.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(
    array(
        'fkooman\\RemoteStorage' => $baseDir.'/src',
        'fkooman\\Rest\\Plugin\\Bearer' => $vendorDir,
        'fkooman\\Rest' => $vendorDir,
        'fkooman\\OAuth\\Common' => $vendorDir,
        'fkooman\\Json' => $vendorDir,
        'fkooman\\Ini' => $vendorDir,
        'fkooman\\Http' => $vendorDir,
        'GuzzleHttp\\Stream' => $vendorDir,
        'GuzzleHttp' => $vendorDir,
    )
);

$loader->register();

# Guzzle 4.0 requirement, should be gone in Guzzle 5.0?
require_once $vendorDir.'/GuzzleHttp/Stream/functions.php';
require_once $vendorDir.'/GuzzleHttp/functions.php';
