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
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Enum\OrderStatusActionTrigger;
use Thelia\Domain\Order\StatusAction\Effect\AllocateInvoiceRefAction;
use Thelia\Domain\Order\StatusAction\OrderStatusActionRunner;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusAction;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * Numbering an invoice is the one gesture on an order that leaves no trace of its
 * own: the allocator saves the order with versioning disabled, precisely so that a
 * legal series is not mirrored into a versions table. The history entry is
 * therefore the only record that the number was posed, and when.
 */
final class InvoiceRefHistoryTest extends ActionIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConfigQuery::write(InvoiceRefAllocator::CONFIG_ENABLED, '1');
    }

    protected function tearDown(): void
    {
        // The row is rolled back with the wrapper transaction, the ConfigQuery static
        // cache is not.
        ConfigQuery::write(InvoiceRefAllocator::CONFIG_ENABLED, '0');

        // Same for the runner: the action rows go with the transaction, the list it
        // read from them stays in the service for the rest of the process.
        $this->getService(OrderStatusActionRunner::class)->reset();

        parent::tearDown();
    }

    public function testTheAllocatedInvoiceNumberIsRecorded(): void
    {
        $order = $this->payOrder($this->factory->order());

        $invoiceRef = $order->getInvoiceRef();
        self::assertNotNull($invoiceRef);

        $entry = $this->invoiceEntry($order);

        self::assertNotNull($entry, 'Posing an invoice number must leave a line in the order history.');
        self::assertSame(['invoice_ref' => $invoiceRef], $entry->getDecodedPayload());
    }

    public function testAnOrderAlreadyNumberedRecordsNothingOnTheNextTransition(): void
    {
        $order = $this->factory->order();
        $order->setInvoiceRef('MODULE-2026-42')->save();

        $this->payOrder($order);

        self::assertNull(
            $this->invoiceEntry($order),
            'A shop numbering its invoices with a module of its own allocates nothing here, so there is nothing to record.',
        );
    }

    public function testTheFeatureBeingOffRecordsNothing(): void
    {
        ConfigQuery::write(InvoiceRefAllocator::CONFIG_ENABLED, '0');

        $order = $this->payOrder($this->factory->order());

        self::assertNull($order->getInvoiceRef());
        self::assertNull($this->invoiceEntry($order));
    }

    public function testTheNumberIsRecordedOnceWhateverTheNumberOfTransitions(): void
    {
        $order = $this->payOrder($this->factory->order());

        $sentStatus = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_SENT);
        self::assertNotNull($sentStatus);

        $event = new OrderEvent($order);
        $event->setStatus($sentStatus->getId());
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertCount(
            1,
            OrderHistoryQuery::create()
                ->filterByOrderId($order->getId())
                ->filterByEventType(OrderHistoryEventType::INVOICE_REF_ALLOCATED->value)
                ->find()
                ->getData(),
        );
    }

    /**
     * The second road to an invoice number, opened by the order status actions.
     *
     * An administrator who hangs the "allocate_invoice_ref" action on a transition
     * asks for the number explicitly, and the action poses it whether or not the
     * automatic numbering setting is on. That road has to leave the same line as the
     * automatic one: the order is saved with versioning disabled either way, so
     * without the entry the numbering of a legal series is nowhere on file.
     */
    public function testANumberPosedByAConfiguredActionIsRecordedWithAutomaticNumberingOff(): void
    {
        ConfigQuery::write(InvoiceRefAllocator::CONFIG_ENABLED, '0');
        $this->allocateInvoiceRefOnEntering(OrderStatus::CODE_PROCESSING);

        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $this->moveOrderTo($order, OrderStatus::CODE_PROCESSING);

        $invoiceRef = OrderQuery::create()->findPk($order->getId())?->getInvoiceRef();
        self::assertNotEmpty($invoiceRef, 'The configured action is what numbers the invoice here.');

        $entry = $this->invoiceEntry($order);

        self::assertNotNull($entry, 'A number posed by a status action must leave a line too.');
        self::assertSame(['invoice_ref' => $invoiceRef], $entry->getDecodedPayload());
    }

    /**
     * Both roads open at once — automatic numbering on and the action configured on
     * the same transition — still number the invoice once and record it once: the
     * listener runs first and the action finds the number already there.
     */
    public function testTheTwoRoadsTogetherLeaveASingleLine(): void
    {
        $this->allocateInvoiceRefOnEntering(OrderStatus::CODE_PAID);

        $order = $this->payOrder($this->factory->order());

        self::assertCount(
            1,
            OrderHistoryQuery::create()
                ->filterByOrderId($order->getId())
                ->filterByEventType(OrderHistoryEventType::INVOICE_REF_ALLOCATED->value)
                ->find()
                ->getData(),
        );
    }

    private function allocateInvoiceRefOnEntering(string $statusCode): void
    {
        $status = OrderStatusQuery::create()->findOneByCode($statusCode);
        self::assertNotNull($status);

        (new OrderStatusAction())
            ->setTriggerType(OrderStatusActionTrigger::ENTER->value)
            ->setToStatusId($status->getId())
            ->setActionType(AllocateInvoiceRefAction::getType())
            ->setDecodedPayload([])
            ->setPosition(1)
            ->setActive(true)
            ->save();

        // The runner keeps the actions of a transition once it has read them, and
        // nothing here dispatches the status events that clear that list.
        $this->getService(OrderStatusActionRunner::class)->reset();
    }

    private function moveOrderTo(Order $order, string $statusCode): void
    {
        $status = OrderStatusQuery::create()->findOneByCode($statusCode);
        self::assertNotNull($status);

        $event = new OrderEvent($order);
        $event->setStatus($status->getId());
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    private function payOrder(Order $order): Order
    {
        $paidStatus = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID);
        self::assertNotNull($paidStatus);

        $event = new OrderEvent($order);
        $event->setStatus($paidStatus->getId());
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

        return $order;
    }

    private function invoiceEntry(Order $order): ?OrderHistory
    {
        return OrderHistoryQuery::create()
            ->findLatestOfType($order->getId(), OrderHistoryEventType::INVOICE_REF_ALLOCATED->value);
    }
}
