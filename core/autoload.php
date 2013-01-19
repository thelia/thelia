<?php

$loader = require __DIR__ . "/vendor/autoload.php";

$loader->add('Thelia', __DIR__ . '/lib/');

if (extension_loaded('apc') && $env == 'prod') {
    $loader->unregister();
    
    require __DIR__ . '/vendor/symfony/class-loader/Symfony/Component/ClassLoader/ApcClassLoader.php';
    
    $apcLoader = new Symfony\Component\ClassLoader\ApcClassLoader("thelia",$loader);
    $apcLoader->register();
    
    return $apcLoader;
}

return $loader;
