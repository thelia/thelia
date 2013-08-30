<?php
use Thelia\Core\Thelia;
use Thelia\Core\HttpFoundation\Request;

//use Symfony\Component\DependencyInjection;

$env = 'dev';
require __DIR__ . '/../core/bootstrap.php';

$trustIp = array(
  '::1',
  '127.0.0.1'
);

$request = Request::createFromGlobals();

if ( false === in_array($request->getClientIp(), $trustIp)) {
    //change request to send to a 404 error page
    exit;
}

$thelia = new Thelia("dev", true);

\Thelia\Tools\URL::retrieveCurrent($request);

$response = $thelia->handle($request)->prepare($request)->send();

$thelia->terminate($request, $response);

echo "\n<!-- page parsed in : " . (microtime(true) - $thelia->getStartTime())." s. -->";
echo "\n<!-- memory peak : " . memory_get_peak_usage()/1024/1024 . " MiB. -->";