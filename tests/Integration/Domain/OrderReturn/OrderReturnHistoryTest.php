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

namespace Thelia\Tests\Integration\Domain\OrderReturn;

use Thelia\Core\Event\OrderReturn\OrderReturnEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\OrderReturn\EventListener\RecordOrderReturnHistoryListener;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderProduct as OrderProductModel;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A return is a second story told about an order, and the merchant reads one
 * timeline. What is checked here is that the three gestures of a return — opening
 * it, moving it between statuses, pointing its reception — leave their line on the
 * order they came from, with the reference that tells two returns of the same order
 * apart, and with nothing in the payload that was typed by a human about a person.
 */
final class OrderReturnHistoryTest extends ActionIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('order_return_enabled', '1');
        ConfigQuery::write('order_return_window_days', '14');
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    public function testOpeningAReturnLeavesALineOnTheOrderItCameFrom(): void
    {
        [$customer, $order, $orderProduct] = $this->paidOrder(quantity: 3.0);

        $return = $this->openReturn($customer, $order, $orderProduct, quantity: 2.0);

        $entries = $this->entriesOfType($order, OrderHistoryEventType::RETURN_OPENED);

        self::assertCount(1, $entries, 'Opening a return must leave exactly one line on the order.');
        self::assertSame(['return_ref' => $return->getRef()], $entries[0]->getDecodedPayload());
    }

    public function testTheLineIsWrittenOnTheOrderTheReturnBelongsTo(): void
    {
        [$customer, $order, $orderProduct] = $this->paidOrder(quantity: 3.0);
        $otherOrder = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);

        $this->openReturn($customer, $order, $orderProduct, quantity: 1.0);

        self::assertCount(1, $this->entriesOf($order));
        self::assertCount(0, $this->entriesOf($otherOrder), 'Another order of the same customer must stay untouched.');
    }

    public function testEachStatusChangeLeavesItsOwnLineWithBothCodes(): void
    {
        [$customer, $order, $orderProduct] = $this->paidOrder(quantity: 3.0);
        $return = $this->openReturn($customer, $order, $orderProduct, quantity: 2.0);

        $this->transition($return, OrderReturnStatus::CODE_ACCEPTED);
        $this->transition($return, OrderReturnStatus::CODE_EXPIRED);

        $entries = $this->entriesOfType($order, OrderHistoryEventType::RETURN_STATUS_CHANGED);

        self::assertCount(2, $entries);
        self::assertSame(
            [
                'return_ref' => $return->getRef(),
                'from' => OrderReturnStatus::CODE_REQUESTED,
                'to' => OrderReturnStatus::CODE_ACCEPTED,
            ],
            $entries[0]->getDecodedPayload(),
        );
        self::assertSame(
            [
                'return_ref' => $return->getRef(),
                'from' => OrderReturnStatus::CODE_ACCEPTED,
                'to' => OrderReturnStatus::CODE_EXPIRED,
            ],
            $entries[1]->getDecodedPayload(),
        );
    }

    public function testPointingTheReceptionIsRecordedAsAReception(): void
    {
        [$customer, $order, $orderProduct] = $this->paidOrder(quantity: 3.0);
        $return = $this->openReturn($customer, $order, $orderProduct, quantity: 2.0);
        $this->transition($return, OrderReturnStatus::CODE_ACCEPTED);

        $line = $return->getOrderReturnLines()->getFirst();
        $event = (new OrderReturnEvent($return))->setReceivedLines([
            (int) $line->getId() => ['quantity' => 2.0, 'condition' => 'as new', 'resellable' => true],
        ]);
        $this->dispatch($event, TheliaEvents::ORDER_RETURN_RECEIVE);

        $entries = $this->entriesOfType($order, OrderHistoryEventType::RETURN_RECEIVED);

        self::assertCount(1, $entries);
        self::assertSame(['return_ref' => $return->getRef()], $entries[0]->getDecodedPayload());

        // The reception moves the return to "received" itself; recording it twice, once
        // as a reception and once as a status change, would say the same thing twice.
        self::assertCount(
            1,
            $this->entriesOfType($order, OrderHistoryEventType::RETURN_STATUS_CHANGED),
            'Only the acceptance is a status change here.',
        );
    }

    public function testTheWholeCycleReadsAsOneTimelineOnTheOrder(): void
    {
        [$customer, $order, $orderProduct] = $this->paidOrder(quantity: 3.0);
        $return = $this->openReturn($customer, $order, $orderProduct, quantity: 2.0);

        $this->transition($return, OrderReturnStatus::CODE_ACCEPTED);

        $line = $return->getOrderReturnLines()->getFirst();
        $this->dispatch(
            (new OrderReturnEvent($return))->setReceivedLines([
                (int) $line->getId() => ['quantity' => 2.0, 'resellable' => true],
            ]),
            TheliaEvents::ORDER_RETURN_RECEIVE,
        );

        $this->transition($return, OrderReturnStatus::CODE_SETTLED);

        self::assertSame(
            [
                OrderHistoryEventType::RETURN_OPENED->value,
                OrderHistoryEventType::RETURN_STATUS_CHANGED->value,
                OrderHistoryEventType::RETURN_RECEIVED->value,
                OrderHistoryEventType::RETURN_STATUS_CHANGED->value,
            ],
            array_map(
                static fn (OrderHistory $entry): string => (string) $entry->getEventType(),
                $this->entriesOf($order),
            ),
        );
    }

    /**
     * A refusal is the one transition the merchant writes words on, and those words
     * are about a customer. They belong to the return, not to a timeline everyone who
     * opens the order sheet can read.
     */
    public function testARefusalRecordsTheCodeAndNotTheWordsTheMerchantWrote(): void
    {
        [$customer, $order, $orderProduct] = $this->paidOrder(quantity: 3.0);
        $return = $this->openReturn($customer, $order, $orderProduct, quantity: 2.0);

        $this->transition($return, OrderReturnStatus::CODE_REFUSED, 'The item came back used, and it smells.');

        $entries = $this->entriesOfType($order, OrderHistoryEventType::RETURN_STATUS_CHANGED);
        self::assertCount(1, $entries);

        self::assertSame(
            [
                'return_ref' => $return->getRef(),
                'from' => OrderReturnStatus::CODE_REQUESTED,
                'to' => OrderReturnStatus::CODE_REFUSED,
            ],
            $entries[0]->getDecodedPayload(),
        );
        self::assertNull($entries[0]->getComment(), 'The refusal wording must not be copied into the history.');
    }

    /**
     * The state machine has no edge from a status onto itself, so the real actions
     * never hand the listener such a change. The guard is still the listener's own
     * contract: a caller that reaches it another way — a module dispatching the event
     * on an already-moved return — must not get a line saying nothing happened.
     */
    public function testAStatusChangeOntoTheSameStatusWritesNothing(): void
    {
        [$customer, $order, $orderProduct] = $this->paidOrder(quantity: 3.0);
        $return = $this->openReturn($customer, $order, $orderProduct, quantity: 2.0);

        $listener = $this->getService(RecordOrderReturnHistoryListener::class);
        $event = new OrderReturnEvent($return);

        $listener->rememberStatusBeforeChange($event);
        $listener->onReturnStatusChanged($event);

        self::assertCount(
            0,
            $this->entriesOfType($order, OrderHistoryEventType::RETURN_STATUS_CHANGED),
        );
    }

    /**
     * @return array{Customer, Order, OrderProductModel}
     */
    private function paidOrder(float $quantity): array
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
        );
        $pse = $this->factory->productSaleElement($product, ['quantity' => 10]);

        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);

        return [$customer, $order, $this->orderProduct($order, $pse, $quantity)];
    }

    /**
     * Opens a return the way every caller does: through ORDER_RETURN_CREATE, which is
     * what numbers it and puts it in its opening status.
     */
    private function openReturn(
        Customer $customer,
        Order $order,
        OrderProductModel $orderProduct,
        float $quantity,
    ): OrderReturn {
        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setCreatedByAdmin(true);

        $return->addOrderReturnLine(
            (new OrderReturnLine())
                ->setOrderProduct($orderProduct)
                ->setQuantity($quantity),
        );

        $this->dispatch(new OrderReturnEvent($return), TheliaEvents::ORDER_RETURN_CREATE);

        return $return;
    }

    private function transition(OrderReturn $return, string $toCode, ?string $refusalReason = null): void
    {
        $target = OrderReturnStatusQuery::create()->findOneByCode($toCode);

        $event = (new OrderReturnEvent($return))
            ->setTargetStatusId((int) $target?->getId())
            ->setRefusalReason($refusalReason);

        $this->dispatch($event, TheliaEvents::ORDER_RETURN_UPDATE_STATUS);
    }

    /**
     * @return list<OrderHistory>
     */
    private function entriesOf(Order $order): array
    {
        /** @var list<OrderHistory> $entries */
        $entries = OrderHistoryQuery::create()
            ->filterByOrderId($order->getId())
            ->orderById()
            ->find($this->getPropelConnection())
            ->getData();

        return $entries;
    }

    /**
     * @return list<OrderHistory>
     */
    private function entriesOfType(Order $order, OrderHistoryEventType $eventType): array
    {
        return array_values(array_filter(
            $this->entriesOf($order),
            static fn (OrderHistory $entry): bool => $eventType->value === $entry->getEventType(),
        ));
    }

    private function orderProduct(Order $order, ProductSaleElements $pse, float $quantity): OrderProductModel
    {
        $orderProduct = (new OrderProductModel())
            ->setOrderId((int) $order->getId())
            ->setProductRef('REF-'.uniqid())
            ->setProductSaleElementsRef((string) $pse->getRef())
            ->setProductSaleElementsId((int) $pse->getId())
            ->setTitle('A returnable product')
            ->setQuantity($quantity)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(1)
            ->setWasInPromo(0)
            ->setVirtual(0);
        $orderProduct->save($this->getPropelConnection());

        return $orderProduct;
    }
}
