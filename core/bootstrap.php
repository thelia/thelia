<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Thelia essential definitions
 */

if (!defined('DS')) {
    define('DS', \DIRECTORY_SEPARATOR);
}

if (!defined('THELIA_ROOT')) {
    define('THELIA_ROOT', rtrim(realpath(dirname(__DIR__)), DS).DS);
}

if (!defined('THELIA_LIB')) {
    define('THELIA_LIB', THELIA_ROOT.'core'.DS.'lib'.DS.'Thelia'.DS);
}

if (!defined('THELIA_VENDOR')) {
    define('THELIA_VENDOR', THELIA_ROOT.'vendor'.DS);
}

if (!defined('THELIA_LOCAL_DIR')) {
    define('THELIA_LOCAL_DIR', THELIA_ROOT.'local'.DS);
}

if (!defined('THELIA_CONF_DIR')) {
    define('THELIA_CONF_DIR', THELIA_LOCAL_DIR.'config'.DS);
}

if (!defined('THELIA_MODULE_DIR')) {
    define('THELIA_MODULE_DIR', THELIA_LOCAL_DIR.'modules'.DS);
}

if (!defined('THELIA_WEB_DIR')) {
    define('THELIA_WEB_DIR', THELIA_ROOT.'web'.DS);
}

if (!defined('THELIA_WEB_ASSETS_DIR')) {
    define('THELIA_WEB_ASSETS_DIR', THELIA_ROOT.'web'.DS.'assets'.DS);
}

if (!defined('THELIA_CACHE_DIR')) {
    define('THELIA_CACHE_DIR', THELIA_ROOT.'var'.DS.'cache'.DS);
}

if (!defined('THELIA_LOG_DIR')) {
    define('THELIA_LOG_DIR', THELIA_ROOT.'var'.DS.'log'.DS);
}

if (!defined('THELIA_TEMPLATE_DIR')) {
    define('THELIA_TEMPLATE_DIR', THELIA_ROOT.'templates'.DS);
}

if (!defined('THELIA_TEMPLATE_FRONTOFFICE_DIR')) {
    define('THELIA_TEMPLATE_FRONTOFFICE_DIR', THELIA_TEMPLATE_DIR.'frontOffice'.DS);
}

if (!defined('THELIA_SETUP_DIRECTORY')) {
    define('THELIA_SETUP_DIRECTORY', THELIA_ROOT.'setup'.DS);
}

if (!defined('THELIA_SETUP_WIZARD_DIRECTORY')) {
    define('THELIA_SETUP_WIZARD_DIRECTORY', THELIA_ROOT.'web'.DS.'install'.DS);
}

if (version_compare(\PHP_VERSION, '7.2', '<')) {
    exit(sprintf(
        "Thelia needs at least php 7.2, but you are using php %s. Please upgrade before using Thelia.\n",
        \PHP_VERSION
    ));
}
