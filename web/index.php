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

//use Thelia\Core\HttpKernel\HttpCache\HttpCache;
use Thelia\Core\Thelia;
use Thelia\Core\HttpFoundation\Request;

//use Symfony\Component\DependencyInjection;

$env = 'prod';
require __DIR__ . '/../core/bootstrap.php';

$request = Request::createFromGlobals();

$thelia = new Thelia("prod", false);
//$thelia = new HttpCache($thelia);
$response = $thelia->handle($request)->prepare($request)->send();

$thelia->terminate($request, $response);
