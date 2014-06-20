<?php

/**
 *
 * @file
 * Functions needed for Thelia bootstrap
 */
ini_set('session.use_cookies', 0);
$env = "test";
require_once __DIR__ . '/../../../bootstrap.php';

use Thelia\Core\Thelia;

$thelia = new Thelia("test", true);
