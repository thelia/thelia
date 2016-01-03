<?php

/**
 *
 * @file
 * Functions needed for Thelia bootstrap
 */
ini_set('session.use_cookies', 0);

if (gc_enabled()) {
    // Disabling Zend Garbage Collection to prevent segfaults with PHP5.4+
    // https://bugs.php.net/bug.php?id=53976
    gc_disable();
}
$env = "test";
require_once __DIR__ . '/../../../../core/vendor/autoload.php';

use Thelia\Core\Thelia;

$thelia = new Thelia("test", true);
