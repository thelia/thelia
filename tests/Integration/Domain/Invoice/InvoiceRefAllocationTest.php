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

namespace Thelia\Tests\Integration\Domain\Invoice;

use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Invoice\InvoiceRefAllocator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class InvoiceRefAllocationTest extends ActionIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConfigQuery::write(InvoiceRefAllocator::CONFIG_ENABLED, '1');
    }

    protected function tearDown(): void
    {
        // The DB row is rolled back with the wrapper transaction, but the
        // ConfigQuery static cache survives across tests: reset it explicitly.
        ConfigQuery::write(InvoiceRefAllocator::CONFIG_ENABLED, '0');

        parent::tearDown();
    }

    public function testPaidOrdersGetConsecutiveYearlyInvoiceRefs(): void
    {
        $firstInvoiceRef = $this->payOrder($this->factory->order())->getInvoiceRef();
        $secondInvoiceRef = $this->payOrder($this->factory->order())->getInvoiceRef();

        $year = date('Y');
        self::assertMatchesRegularExpression("/^{$year}-\\d{6}$/", (string) $firstInvoiceRef);

        [, $firstNumber] = explode('-', (string) $firstInvoiceRef);
        [, $secondNumber] = explode('-', (string) $secondInvoiceRef);

        self::assertSame(
            (int) $firstNumber + 1,
            (int) $secondNumber,
            'Invoice numbers must be strictly consecutive within the yearly series.',
        );
    }

    public function testAlreadyPaidStatusTransitionDoesNotReallocate(): void
    {
        $order = $this->payOrder($this->factory->order());
        $allocatedRef = $order->getInvoiceRef();

        $sentStatus = OrderStatusQuery::create()->findOneByCode('sent');
        self::assertNotNull($sentStatus);

        $event = new OrderEvent($order);
        $event->setStatus($sentStatus->getId());
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertSame($allocatedRef, OrderQuery::create()->findPk($order->getId())->getInvoiceRef());
    }

    public function testModuleManagedInvoiceRefIsNeverOverwritten(): void
    {
        $order = $this->factory->order();
        $order->setInvoiceRef('MODULE-2026-42')->save();

        $this->payOrder($order);

        self::assertSame('MODULE-2026-42', OrderQuery::create()->findPk($order->getId())->getInvoiceRef());
    }

    public function testDisabledConfigLeavesInvoiceRefNull(): void
    {
        ConfigQuery::write(InvoiceRefAllocator::CONFIG_ENABLED, '0');

        $order = $this->payOrder($this->factory->order());

        self::assertNull($order->getInvoiceRef());
    }

    public function testUnpaidTransitionAllocatesNothing(): void
    {
        $order = $this->factory->order();
        $canceledStatus = OrderStatusQuery::create()->findOneByCode('canceled');
        self::assertNotNull($canceledStatus);

        $event = new OrderEvent($order);
        $event->setStatus($canceledStatus->getId());
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertNull(OrderQuery::create()->findPk($order->getId())->getInvoiceRef());
    }

    public function testDuplicatePaymentNotificationBurnsNoNumber(): void
    {
        $order = $this->payOrder($this->factory->order());
        $allocatedRef = $order->getInvoiceRef();
        self::assertNotNull($allocatedRef);

        // Simulate the duplicate payment notification race: another process
        // loaded the order before it was numbered, so its in-memory
        // invoice_ref is still empty while the column is already set.
        $stale = OrderQuery::create()->findPk($order->getId());
        $stale->setInvoiceRef(null);

        $this->getService(InvoiceRefAllocator::class)->allocate($stale);

        $statement = $this->getPropelConnection()->prepare('SELECT `invoice_ref` FROM `order` WHERE `id` = ?');
        $statement->execute([$order->getId()]);
        self::assertSame($allocatedRef, $statement->fetchColumn(), 'The ref allocated first must never be overwritten.');

        // The aborted reallocation must not have consumed a number: the next
        // invoice of the series is strictly consecutive.
        $nextInvoiceRef = $this->payOrder($this->factory->order())->getInvoiceRef();

        [, $allocatedNumber] = explode('-', (string) $allocatedRef);
        [, $nextNumber] = explode('-', (string) $nextInvoiceRef);

        self::assertSame(
            (int) $allocatedNumber + 1,
            (int) $nextNumber,
            'A refused duplicate allocation must leave no hole in the series.',
        );
    }

    public function testCustomFormatIsApplied(): void
    {
        ConfigQuery::write(InvoiceRefAllocator::CONFIG_FORMAT, 'FAC%year%/%number%');

        try {
            $order = $this->payOrder($this->factory->order());

            self::assertMatchesRegularExpression('/^FAC\d{4}\/\d{6}$/', (string) $order->getInvoiceRef());
        } finally {
            ConfigQuery::write(InvoiceRefAllocator::CONFIG_FORMAT, InvoiceRefAllocator::DEFAULT_FORMAT);
        }
    }

    private function payOrder(Order $order): Order
    {
        $paidStatus = OrderStatusQuery::create()->findOneByCode('paid');
        self::assertNotNull($paidStatus);

        $event = new OrderEvent($order);
        $event->setStatus($paidStatus->getId());
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

        return OrderQuery::create()->findPk($order->getId());
    }
}
