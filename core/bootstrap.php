<?php

/**
 *
 * @file
 * Functions needed for Thelia bootstrap
 */
define('THELIA_ROOT'         , rtrim(realpath(__DIR__ .'/../'),'/') . "/");
define('THELIA_LOCAL_DIR'    , THELIA_ROOT . 'local/');
define('THELIA_CONF_DIR'     , THELIA_LOCAL_DIR . 'config/');
define('THELIA_MODULE_DIR'   , THELIA_LOCAL_DIR . 'modules/');
define('THELIA_WEB_DIR'      , THELIA_ROOT . 'web/');
define('THELIA_TEMPLATE_DIR' , THELIA_ROOT . 'templates/');
define('DS'                  , DIRECTORY_SEPARATOR);

$loader = require __DIR__ . "/vendor/autoload.php";

if (!file_exists(THELIA_CONF_DIR . 'database.yml') && !defined('THELIA_INSTALL_MODE')) {
    $sapi = php_sapi_name();
    if (substr($sapi, 0, 3) == 'cli') {
        define('THELIA_INSTALL_MODE', true);
    } else {
        $request = \Thelia\Core\HttpFoundation\Request::createFromGlobals();
        header('location: '.$request->getSchemeAndHttpHost() . '/install');
        exit;
    }
}