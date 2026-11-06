<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Thelia\Domain\Catalog\Stock\CallerTransactionInterface;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

// Loaded by App\Kernel in the test environment only.
return static function (ContainerConfigurator $container): void {
    // Every suite reaches the shop from the same address: the budget of holds by address, which
    // protects a shop from one caller, would run out in the middle of a run. The budget of a cart
    // is the shop's own.
    $container->parameters()->set('env(THELIA_STOCK_RESERVATION_RATE_LIMIT_PER_CLIENT)', '1000000');

    // The suites roll their fixtures back in a transaction of their own, which is no caller's:
    // the levels a test declares open are not counted.
    $container->services()
        ->set(TestCallerTransaction::class)
        ->decorate(CallerTransactionInterface::class)
        ->args([service('.inner')])
        ->public();
};
