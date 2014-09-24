<?php
$vendorDir = '/usr/share/php';
$baseDir   = dirname(__DIR__);

require_once $vendorDir . '/Symfony/Component/ClassLoader/UniversalClassLoader.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(
    array(
        'fkooman\\RemoteStorage' $baseDir . '/src'),
        'fkooman\\Rest' => $vendorDir,
        'fkooman\\OAuth\\ResourceServer' => $vendorDir,
        'fkooman\\OAuth\\Common' => $vendorDir,
        'fkooman\\Json' => $vendorDir,
        'fkooman\\Http' => $vendorDir,
        'fkooman\\Config' => $vendorDir,
        'Symfony\\Component\\Yaml' => $vendorDir,
        'Symfony\\Component\\EventDispatcher' => $vendorDir,
        #'Guzzle\\Tests' => $vendorDir,
        'Guzzle' => $vendorDir
    )
);

$loader->register();
