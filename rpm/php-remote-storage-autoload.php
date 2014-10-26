<?php
$vendorDir = '/usr/share/php';
$baseDir   = dirname(__DIR__);

require_once $vendorDir.'/Symfony/Component/ClassLoader/UniversalClassLoader.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(
    array(
        'fkooman\\RemoteStorage'              => $baseDir.'/src',
        'fkooman\\Rest\\Plugin\\Bearer'       => $vendorDir,
        'fkooman\\Rest'                       => $vendorDir,
        'fkooman\\Json'                       => $vendorDir,
        'fkooman\\Ini'                        => $vendorDir,
        'fkooman\\Http'                       => $vendorDir,
        'Guzzle'                              => $vendorDir,
        'Symfony\\Component\\EventDispatcher' => $vendorDir,
    )
);

$loader->register();
