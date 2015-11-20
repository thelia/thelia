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
use Symfony\Component\ClassLoader\ApcClassLoader;
use Thelia\Core\Thelia;
use Thelia\Core\HttpFoundation\Request;

//use Symfony\Component\DependencyInjection;

$env = 'prod';
$loader = require __DIR__ . '/../core/vendor/autoload.php';

// Enable APC for autoloading to improve performance.
// You should change the ApcClassLoader first argument to a unique prefix
// in order to prevent cache key conflicts with other applications
// also using APC.
/*
$cacheLoader = new ApcClassLoader(sha1(__FILE__), $loader);
$loader->unregister();
$cacheLoader->register(true);
*/


$request = Request::createFromGlobals();

$thelia = new Thelia("prod", false);
//$thelia = new HttpCache($thelia);
$response = $thelia->handle($request)->prepare($request)->send();

$thelia->terminate($request, $response);
