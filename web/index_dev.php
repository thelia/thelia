<?php
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Thelia;

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
}

$thelia = new Thelia("dev", true);

$response = $thelia->handle($request)->prepare($request)->send();

$thelia->terminate($request, $response);

echo "page parsed in : " . (microtime(true) - $thelia->getStartTime());