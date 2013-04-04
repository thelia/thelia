<?php

/**
 *
 * @file
 * Functions needed for Thelia bootstrap
 */
$env = "test";
require_once __DIR__ . '/../../../bootstrap.php';

use Thelia\Core\Thelia;

$thelia = new Thelia("test", true);




