<?php

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Thelia;

//use Symfony\Component\DependencyInjection;

$env = 'prod';
require __DIR__ . '/../core/bootstrap.php';

$request = Request::createFromGlobals();

$thelia = new Thelia($env, false);

$response = $thelia->handle($request)->prepare($request)->send();

$thelia->terminate($request, $response);
