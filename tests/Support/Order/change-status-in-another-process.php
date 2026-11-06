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
 * Changes the status of an order from a process of its own, the way a payment module does through
 * the status event (its own kernel, its own connection, the listeners of the core), and stays in
 * its transaction for a while once it holds its locks: the process of the test then asks for the
 * same order, the way the admin API does. Run by OrderStatusLockOrderApiTest with the environment
 * of the test process, so that it works on the same database.
 *
 * Usage: php change-status-in-another-process.php pay <order id> <status id> <marker file> <pause in ms>
 *        php change-status-in-another-process.php pay-twice <order id> <status id> <marker file> <timeout in ms>
 *        php change-status-in-another-process.php wrong-order <order id> <sale element id> <marker file> <pause in ms>
 *
 * "pay-twice" is a payment confirmed twice (the notification of the server and the return of the
 * browser): it reads the order twice, writes the marker, waits for the file "<marker>.go" (the test
 * pays the order meanwhile), and then confirms the payment with each of the two models it read, as
 * they were before the other request paid. It also prints how many times the stock levels were told.
 *
 * "pay" pauses just before the order row is written, in the status event. "wrong-order" takes the
 * locks the other way round on purpose, the stock row and then the order row, after having written
 * enough rows to weigh more than the other transaction, so that the database breaks the tie against
 * the other one. Prints, as JSON, the outcome and the class of the failure.
 */

use App\Kernel;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Order\Exception\OrderStatusChangeConflictException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Event\OrderEvent as OrderModelEvent;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderQuery;

require dirname(__DIR__, 3).'/tests/bootstrap.php';

[$mode, $orderId, $second, $marker, $pause] = [$argv[1] ?? '', (int) ($argv[2] ?? 0), (int) ($argv[3] ?? 0), (string) ($argv[4] ?? ''), (int) ($argv[5] ?? 2500)];

$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');

$container->get('thelia.translator');
$container->get('thelia.url.manager');
$request = Request::create('http://localhost');
$request->setSession(new Session(new MockArraySessionStorage()));
$container->get('request_stack')->push($request);

// The settings the test committed, read from the database rather than from a warmed cache.
ConfigQuery::resetCache();

$dispatcher = $container->get(EventDispatcherInterface::class);
$dispatcher->addListener(
    TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'),
    static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(false),
    512,
);

$stayInTheTransaction = static function () use ($marker, $pause): void {
    file_put_contents($marker, (string) getmypid());
    usleep($pause * 1000);
};

$outcome = ['outcome' => 'done', 'failure' => null];
$stockLevelsTold = 0;
$dispatcher->addListener(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, static function (StockLevelChangedEvent $event) use (&$stockLevelsTold): void {
    ++$stockLevelsTold;
});

try {
    if ('pay-twice' === $mode) {
        // The two requests read the order as it is before anyone pays it: models of their own.
        $orders = [
            OrderQuery::create()->findPk($orderId) ?? throw new RuntimeException('No order '.$orderId),
            OrderQuery::create()->findPk($orderId) ?? throw new RuntimeException('No order '.$orderId),
        ];
        // Their status is read now, as the first thing a confirmation does with the order.
        foreach ($orders as $order) {
            $order->getOrderStatus();
        }

        file_put_contents($marker, (string) getmypid());
        $deadline = microtime(true) + $pause / 1000;

        while (!is_file($marker.'.go')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Never told to go on.');
            }

            usleep(50000);
        }

        foreach ($orders as $order) {
            $dispatcher->dispatch((new OrderEvent($order))->setStatus($second), TheliaEvents::ORDER_UPDATE_STATUS);
        }
    } elseif ('pay' === $mode) {
        $order = OrderQuery::create()->findPk($orderId) ?? throw new RuntimeException('No order '.$orderId);
        $paused = false;

        // The write of the order is the last thing the status event does inside its transaction.
        $dispatcher->addListener(OrderModelEvent::PRE_UPDATE, static function (OrderModelEvent $event) use ($orderId, &$paused, $stayInTheTransaction): void {
            if ($paused || (int) $event->getModel()->getId() !== $orderId) {
                return;
            }

            $paused = true;
            $stayInTheTransaction();
        });

        $dispatcher->dispatch((new OrderEvent($order))->setStatus($second), TheliaEvents::ORDER_UPDATE_STATUS);
    } elseif ('wrong-order' === $mode) {
        $connection = Propel::getWriteConnection(OrderTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $connection->query('SELECT `id` FROM `product_sale_elements` WHERE `id` = '.$second.' FOR UPDATE')->fetchAll();

            for ($row = 0; $row < 30; ++$row) {
                $connection->exec('INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES (\'status-lock-weight-'.$orderId.'-'.$row.'\', \'1\', 0, 0, NOW(), NOW())');
            }

            $stayInTheTransaction();
            $connection->exec('UPDATE `order` SET `updated_at` = NOW() WHERE `id` = '.$orderId);
            $connection->commit();
        } catch (Throwable $failure) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $failure;
        }
    } else {
        throw new InvalidArgumentException('Unknown mode '.$mode);
    }
} catch (OrderStatusChangeConflictException $conflict) {
    $outcome = ['outcome' => 'conflict', 'failure' => $conflict::class];
} catch (Throwable $failure) {
    $outcome = ['outcome' => 'failed', 'failure' => $failure::class.': '.$failure->getMessage()];
}

if ('pay-twice' === $mode) {
    $outcome['stockLevelsTold'] = $stockLevelsTold;
}

echo json_encode($outcome, \JSON_THROW_ON_ERROR);

$kernel->shutdown();
