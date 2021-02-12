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

use Symfony\Component\Dotenv\Dotenv;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Thelia;

$env = 'dev';
require __DIR__.'/../vendor/autoload.php';

if (file_exists(THELIA_ROOT.'.env')) {
    (new Dotenv())->load(THELIA_ROOT.'.env');
}

// List of allowed IP
$trustedIp = [
  '::1',
  '127.0.0.1',
];

$request = Request::createFromGlobals();

if (false && false === in_array($request->getClientIp(), $trustedIp)) {
    header('HTTP/1.0 403 Forbidden');
    exit('You are not allowed to access this file.');
}

$thelia = new Thelia('dev', true);

$response = $thelia->handle($request)->prepare($request)->send();
$thelia->terminate($request, $response);
