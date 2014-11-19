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
define('DS'                  , DIRECTORY_SEPARATOR);
define('THELIA_ROOT'         , rtrim(realpath(dirname(__DIR__)), DS) . DS);
define('THELIA_LOCAL_DIR'    , THELIA_ROOT . 'local' . DS);
define('THELIA_CONF_DIR'     , THELIA_LOCAL_DIR . 'config' . DS);
define('THELIA_MODULE_DIR'   , THELIA_LOCAL_DIR . 'modules' . DS);
define('THELIA_WEB_DIR'      , THELIA_ROOT . 'web' . DS);
define('THELIA_CACHE_DIR'    , THELIA_ROOT . 'cache' . DS);
define('THELIA_LOG_DIR'      , THELIA_ROOT . 'log' . DS);
define('THELIA_TEMPLATE_DIR' , THELIA_ROOT . 'templates' . DS);

$loader = require __DIR__ . "/vendor/autoload.php";

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
