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
 * Cancels an order from a process of its own, the way a second request does: its own
 * kernel, its own connection, its own instance of the payment modules. Run by
 * OrderPaymentPartReleaseConcurrencyTest with the environment of the test process, so that
 * it works on the same database. Prints, as JSON, what the instant test modules were asked
 * by this process.
 *
 * Usage: php cancel-order-in-another-process.php <order id> [marker file]
 *
 * With a marker file, the process writes it when it is asked to release a part, which it
 * does under the lock of the order, then holds the lock a little longer: the test waits
 * for the file before it competes for the lock. The moment the release ended, still under
 * the lock, is printed as releasedAt.
 */

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\OrderQuery;
use Thelia\Tests\Support\Payment\BalanceChargingTestPaymentModule;
use Thelia\Tests\Support\Payment\InstantTestPaymentModule;
use Thelia\Tests\Support\Payment\SecondInstantTestPaymentModule;

require dirname(__DIR__, 3).'/tests/bootstrap.php';

$orderId = (int) ($argv[1] ?? 0);
$markerFile = $argv[2] ?? null;

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
$releasedAt = null;

if (null !== $markerFile) {
    InstantTestPaymentModule::$whenAskedToRelease = static function () use ($markerFile, &$releasedAt): void {
        file_put_contents($markerFile, (string) getmypid());
        usleep(1500000);
        $releasedAt = microtime(true);
    };
}

$order = OrderQuery::create()->findPk($orderId) ?? throw new RuntimeException('No order '.$orderId);
$order->setCancelled($container->get(EventDispatcherInterface::class));

echo json_encode([
    'calls' => InstantTestPaymentModule::$calls,
    'releasedPartIds' => InstantTestPaymentModule::$releasedPartIds,
    'releasedAt' => $releasedAt,
], \JSON_THROW_ON_ERROR);

$kernel->shutdown();
