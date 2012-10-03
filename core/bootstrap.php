<?php

if(!isset($env)){
    $env = 'prod';
}

use Symfony\Component\DependencyInjection;
use Symfony\Component\DependencyInjection\Reference;


/**
 * 
 * @file 
 * Functions needed for Thelia bootstrap
 */


$loader = require __DIR__ . '/autoload.php';


?>
