<?php

/**
 *
 * @file
 * Functions needed for Thelia bootstrap
 */
define('THELIA_ROOT', realpath(__DIR__ .'/../') . "/");
define('THELIA_CONF_DIR', THELIA_ROOT . '/local/config');
define('THELIA_MODULE_DIR', THELIA_ROOT . '/local/modules');
define('THELIA_TEMPLATE_DIR', THELIA_ROOT . 'templates/');
$loader = require __DIR__ . "/vendor/autoload.php";



if (!file_exists(THELIA_ROOT . '/local/config/database.yml')) {
    define('THELIA_INSTALL_MODE',true);
}
/*else {
    define('THELIA_INSTALL_MODE',true);
}*/
