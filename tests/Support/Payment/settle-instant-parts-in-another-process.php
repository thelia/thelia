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

/*
 * Settles the instant parts of an order from a process of its own, the way a second request
 * does: its own kernel, its own connection, its own instance of the payment modules. Run by
 * OrderPaymentPartReleaseConcurrencyTest with the environment of the test process, so that
 * it works on the same database.
 *
 * The instant test module writes a debit, a `config` row named after the order, on the
 * connection of the core while it settles the part: it commits with the settlement, the way
 * the balance of a gift card does, and it is what a module asked to give the part back
 * must find.
 *
 * Usage: php settle-instant-parts-in-another-process.php <order id> <debit name>
 *
 * Prints, as JSON: what the modules were asked, and "outcome", "settled" or "refused" when
 * the order was found cancelled.
 */

use App\Kernel;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Payment\Exception\CancelledOrderPaymentRefusedException;
use Thelia\Domain\Payment\Service\OrderPaymentPartSettlement;
use Thelia\Model\Map\OrderPaymentPartTableMap;
use Thelia\Model\OrderQuery;
use Thelia\Tests\Support\Payment\BalanceChargingTestPaymentModule;
use Thelia\Tests\Support\Payment\InstantTestPaymentModule;
use Thelia\Tests\Support\Payment\SecondInstantTestPaymentModule;

require dirname(__DIR__, 3).'/tests/bootstrap.php';

$orderId = (int) ($argv[1] ?? 0);
$debitName = (string) ($argv[2] ?? '');

$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');

$container->get('thelia.translator');
$container->get('thelia.url.manager');
$request = Request::create('http://localhost');
$request->setSession(new Session(new MockArraySessionStorage()));
$container->get('request_stack')->push($request);

foreach ([InstantTestPaymentModule::class, SecondInstantTestPaymentModule::class, BalanceChargingTestPaymentModule::class] as $moduleClass) {
    $instance = new $moduleClass();
    $instance->setContainer($container);
    $container->set('module.'.$moduleClass::getModuleCode(), $instance);
}

InstantTestPaymentModule::reset();
InstantTestPaymentModule::$whenAskedToSettle = static function () use ($debitName): void {
    $debit = Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME)->prepare(
        'INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES (:name, :value, 0, 1, NOW(), NOW())',
    );
    $debit->execute([':name' => $debitName, ':value' => '30']);
};

$order = OrderQuery::create()->findPk($orderId) ?? throw new RuntimeException('No order '.$orderId);
$outcome = 'settled';

try {
    $container->get(OrderPaymentPartSettlement::class)->settleInstantPartsOf($order);
} catch (CancelledOrderPaymentRefusedException) {
    $outcome = 'refused';
}

echo json_encode([
    'calls' => InstantTestPaymentModule::$calls,
    'settledPartIds' => InstantTestPaymentModule::$settledPartIds,
    'outcome' => $outcome,
], \JSON_THROW_ON_ERROR);

$kernel->shutdown();
