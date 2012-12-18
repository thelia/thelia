<?php

if (!isset($env)) {
    $env = 'prod';
}

/**
 *
 * @file
 * Functions needed for Thelia bootstrap
 */

$loader = require __DIR__ . '/autoload.php';

define('THELIA_ROOT', __DIR__ .'/../');

if(file_exists(THELIA_ROOT . '/local/config/config_db.php'))
{
    require THELIA_ROOT . '/local/config/config_db.php';
} else {
    define('THELIA_INSTALL_MODE',true);
}
