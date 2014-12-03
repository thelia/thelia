<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

/**
 * Thelia essential definitions
 */

$loader = require __DIR__ . DS . "vendor" . DS . "autoload.php";

define("DS", DIRECTORY_SEPARATOR);

if (!defined('THELIA_ROOT')) {
    define('THELIA_ROOT', rtrim(realpath(dirname(__DIR__)), DS) . DS);
}

if (!defined('THELIA_LOCAL_DIR')) {
    define('THELIA_LOCAL_DIR', THELIA_ROOT . 'local' . DS);
}

if (!defined('THELIA_CONF_DIR')) {
    define('THELIA_CONF_DIR', THELIA_LOCAL_DIR . 'config' . DS);
}

if (!defined('THELIA_MODULE_DIR')) {
    define('THELIA_MODULE_DIR', THELIA_LOCAL_DIR . 'modules' . DS);
}

if (!defined('THELIA_WEB_DIR')) {
    define('THELIA_WEB_DIR', THELIA_ROOT . 'web' . DS);
}

if (!defined('THELIA_CACHE_DIR')) {
    define('THELIA_CACHE_DIR', THELIA_ROOT . 'cache' . DS);
}

if (!defined('THELIA_LOG_DIR')) {
    define('THELIA_LOG_DIR', THELIA_ROOT . 'log' . DS);
}

if (!defined('THELIA_TEMPLATE_DIR')) {
    define('THELIA_TEMPLATE_DIR', THELIA_ROOT . 'templates' . DS);
}

if (!file_exists(THELIA_CONF_DIR . 'database.yml') && !defined('THELIA_INSTALL_MODE')) {
    $sapi = php_sapi_name();
    if (substr($sapi, 0, 3) == 'cli') {
        define('THELIA_INSTALL_MODE', true);
    } else {
        $request = \Thelia\Core\HttpFoundation\Request::createFromGlobals();
        header('Location: '.$request->getUriForPath('/install'));
    }
}

return $loader;
