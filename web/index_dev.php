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
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpKernel\HttpCache\HttpCache;

//use Symfony\Component\DependencyInjection;

$env = 'dev';
require __DIR__ . '/../core/vendor/autoload.php';

// List of allowed IP
$trustedIp = array(
  '::1',
  '127.0.0.1',
);

$request = Request::createFromGlobals();
$thelia = new Thelia("dev", true);

if (false === in_array($request->getClientIp(), $trustedIp)) {
    $response = Response::create('Forbidden', 403)->send();
    $thelia->terminate($request, $response);
} else {
    $response = $thelia->handle($request)->prepare($request)->send();
    $thelia->terminate($request, $response);
}
