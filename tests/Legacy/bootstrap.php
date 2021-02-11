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

ini_set('session.use_cookies', 0);

if (gc_enabled()) {
    // Disabling Zend Garbage Collection to prevent segfaults with PHP5.4+
    // https://bugs.php.net/bug.php?id=53976
    gc_disable();
}
$env = "test";
require_once __DIR__ . '/../../vendor/autoload.php';

use Thelia\Core\Thelia;

$thelia = new Thelia("test", true);
$thelia->boot();
