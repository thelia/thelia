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

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;

ini_set('session.use_cookies', 0);

if (gc_enabled()) {
    // Disabling Zend Garbage Collection to prevent segfaults with PHP5.4+
    // https://bugs.php.net/bug.php?id=53976
    gc_disable();
}

require_once __DIR__.'/../../vendor/autoload.php';

// The database credentials live in the .env files, and Thelia::isInstalled()
// reads them from $_SERVER. Without this the boot below dies on
// "Thelia is not installed".
(new Dotenv())->bootEnv(__DIR__.'/../../.env');

// App\Kernel, not Thelia\Core\Thelia: only App\Kernel::configureContainer()
// imports config/packages/*.yaml, where the bundles installed by Flex are
// configured.
$kernel = new Kernel('test', true);
$kernel->boot();

// The tests dispatch real Thelia events, and the listeners behind them expect a
// current request: Thelia\Action\Coupon::updateOrderDiscount() for instance
// reads the session from it. Nothing pushes one here, so do it once for the
// whole suite.
$request = new Request();
$request->setSession(new Session(new MockArraySessionStorage()));
$kernel->getContainer()->get('request_stack')->push($request);

// Translator and URL are used through their singleton accessors all over the
// core. The HTTP kernel instantiates their service early for that reason, and
// the tests need the same.
$kernel->getContainer()->get('thelia.translator');
$kernel->getContainer()->get('thelia.url.manager');
