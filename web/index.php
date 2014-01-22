<?php
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
