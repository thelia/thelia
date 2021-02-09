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

use Symfony\Component\Dotenv\Dotenv;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Thelia;

$env = 'prod';
$loader = require __DIR__ . '/../vendor/autoload.php';

if (file_exists(THELIA_ROOT.'.env')) {
    (new Dotenv())->load(THELIA_ROOT.'.env');
}

$request = Request::createFromGlobals();

$thelia = new Thelia("prod", false);

//$thelia = new HttpCache($thelia);

// When using the HttpCache, you need to call the method in your front controller instead of relying on the configuration parameter
//Request::enableHttpMethodParameterOverride();

$response = $thelia->handle($request)->prepare($request)->send();

$thelia->terminate($request, $response);
