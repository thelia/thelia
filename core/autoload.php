<?php

require __DIR__ . '/vendor/symfony/class-loader/Symfony/Component/ClassLoader/UniversalClassLoader.php';
require __DIR__ . '/lib/Thelia/Autoload/TheliaUniversalClassLoader.php';
require __DIR__ . '/lib/Thelia/Autoload/TheliaApcUniversalClassLoader.php';

use Thelia\Autoload\TheliaUniversalClassLoader;
use Thelia\Autoload\TheliaApcUniversalClassLoader;

if (extension_loaded('apc') && $env == 'prod') {
    $loader = new TheliaApcUniversalClassLoader('Thelia');
} else {
    $loader = new TheliaUniversalClassLoader();
}

$namespaces = require __DIR__ . '/vendor/composer/autoload_namespaces.php';

foreach ($namespaces as $namespace => $directory) {
    $loader->registerNamespace($namespace, $directory);
}

$loader->registerNamespace('Thelia', __DIR__ . '/lib/');

if(file_exists(__DIR__ . '/vendor/composer/autoload_classmap.php'))
{
    $classMap = require __DIR__ . '/vendor/composer/autoload_classmap.php';
        
    $loader->addClassMap($classMap);
}
//
//$loader->addClassMap(array(
//    'NotORM' => THELIA_ROOT . '/core/vendor/vrana/notorm/NotORM.php',
//    'NotORM_Cache_Session' => THELIA_ROOT . '/core/vendor/vrana/notorm/NotORM/Cache.php',
//    'NotORM_Cache_File' => THELIA_ROOT . '/core/vendor/vrana/notorm/NotORM/Cache.php',
//    'NotORM_Cache_Include' => THELIA_ROOT . '/core/vendor/vrana/notorm/NotORM/Cache.php',
//    'NotORM_Cache_Database' => THELIA_ROOT . '/core/vendor/vrana/notorm/NotORM/Cache.php',
//    'NotORM_Cache_Memcache' => THELIA_ROOT . '/core/vendor/vrana/notorm/NotORM/Cache.php',
//    'NotORM_Cache_APC' => THELIA_ROOT . '/core/vendor/vrana/notorm/NotORM/Cache.php',
//    'NotORM_Literal' => THELIA_ROOT . '/core/vendor/vrana/notorm/NotORM/Literal.php',
//    'NotORM_MultiResult' => THELIA_ROOT . '/core/vendor/vrana/notorm/NotORM/MultiResult.php',
//    'NotORM_Result' => THELIA_ROOT . '/core/vendor/vrana/notorm/NotORM/Result.php',
//    'NotORM_Row' => THELIA_ROOT . '/core/vendor/vrana/notorm/NotORM/Row.php',
//    'NotORM_Structure_Convention' => THELIA_ROOT . '/core/vendor/vrana/notorm/NotORM/Structure.php',
//    'NotORM_Structure_Discovery' => THELIA_ROOT . '/core/vendor/vrana/notorm/NotORM/Structure.php',
//));

$loader->register();
