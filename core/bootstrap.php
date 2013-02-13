<?php

/**
 *
 * @file
 * Functions needed for Thelia bootstrap
 */
define('THELIA_ROOT', realpath(__DIR__ .'/../') . "/");
define('THELIA_CONF_DIR', THELIA_ROOT . '/local/config');
define('THELIA_PLUGIN_DIR', THELIA_ROOT . '/local/plugins');
define('THELIA_TEMPLATE_DIR', THELIA_ROOT . 'templates/');
$loader = require __DIR__ . "/vendor/autoload.php";



if (file_exists(THELIA_ROOT . '/local/config/config_db.php')) {
    require THELIA_ROOT . '/local/config/config_db.php';
} else {
    define('THELIA_INSTALL_MODE',true);
}
