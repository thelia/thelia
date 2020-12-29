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

use Thelia\Core\Thelia;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\Dotenv\Dotenv;

$env = 'dev';
require __DIR__ . '/../core/vendor/autoload.php';

if (file_exists(THELIA_ROOT.'.env')) {
    (new Dotenv())->load(THELIA_ROOT.'.env');
}

// List of allowed IP
$trustedIp = array(
  '::1',
  '127.0.0.1',
);

$request = Request::createFromGlobals();

if (false && false === in_array($request->getClientIp(), $trustedIp)) {
    header('HTTP/1.0 403 Forbidden');
    exit('You are not allowed to access this file.');
}

$thelia = new Thelia("dev", true);

if (PHP_VERSION_ID < 70000) {
    $thelia->loadClassCache();
}

$response = $thelia->handle($request)->prepare($request)->send();
$thelia->terminate($request, $response);
