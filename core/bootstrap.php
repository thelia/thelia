<?php

if (!isset($env)) {
    $env = 'prod';
}

/**
 *
 * @file
 * Functions needed for Thelia bootstrap
 */
define('THELIA_ROOT', __DIR__ .'/../');
$loader = require __DIR__ . '/autoload.php';



if(file_exists(THELIA_ROOT . '/local/config/config_db.php'))
{
    require THELIA_ROOT . '/local/config/config_db.php';
} else {
    define('THELIA_INSTALL_MODE',true);
}
