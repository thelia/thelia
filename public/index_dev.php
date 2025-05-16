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
use Symfony\Component\ErrorHandler\Debug;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Thelia;

require dirname(__DIR__).'/vendor/autoload.php';

$_SERVER['APP_ENV'] = 'dev';
$_SERVER['APP_DEBUG'] = '1';

(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

$trustedIp = array_filter(
    explode(',', $_SERVER['DEBUG_TRUSTED_IP'] ?? ''),
    static function ($ip): bool {
        return filter_var($ip, \FILTER_VALIDATE_IP);
    }
);

if (false === in_array(Request::createFromGlobals()->getClientIp(), $trustedIp)) {
    header('HTTP/1.0 403 Forbidden');
    exit('You are not allowed to access this file.');
}

umask(0000);
Debug::enable();

$thelia = new App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$request = Request::createFromGlobals();
$response = $thelia->handle($request);
$response->send();
$thelia->terminate($request, $response);
