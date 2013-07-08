<?php
use Thelia\Core\Thelia;
use Thelia\Core\HttpFoundation\Request;

//use Symfony\Component\DependencyInjection;

$env = 'prod';
require __DIR__ . '/../core/bootstrap.php';

$request = Request::createFromGlobals();

$thelia = new Thelia("prod", false);

$response = $thelia->handle($request)->prepare($request)->send();

$thelia->terminate($request, $response);
