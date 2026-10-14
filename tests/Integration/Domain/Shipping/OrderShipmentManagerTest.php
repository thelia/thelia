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

namespace Thelia\Tests\Integration\Domain\Shipping;

use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Shipping\DTO\ShipmentLine;
use Thelia\Domain\Shipping\Exception\ShipmentNotAllowedException;
use Thelia\Domain\Shipping\Service\OrderShipmentManager;
use Thelia\Domain\Shipping\Service\ShippedQuantityCalculator;
use Thelia\Model\Order;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderShipment;
use Thelia\Model\OrderShipmentQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\MovesOrders;

/**
 * An order leaving in several parcels: what each shipment may carry, what the
 * order does as they leave, and what an order shipped the old way looks like.
 */
final class OrderShipmentManagerTest extends ActionIntegrationTestCase
{
    use MovesOrders;

    private OrderShipmentManager $manager;

    private ShippedQuantityCalculator $quantities;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->getService(OrderShipmentManager::class);
        $this->quantities = $this->getService(ShippedQuantityCalculator::class);
    }

    public function testADraftHoldsItsLinesAgainstWhatIsLeftToShip(): void
    {
        [$order, $first, $second] = $this->paidOrderOfTwoLines(3.0, 2.0);

        $shipment = $this->manager->open($order, [new ShipmentLine((int) $first->getId(), 2.0)]);

        self::assertTrue($shipment->isDraft());
        self::assertSame($order->getRef().'-1', $shipment->getRef());
        self::assertSame(
            [(int) $first->getId() => 1.0, (int) $second->getId() => 2.0],
            $this->quantities->remainingByOrderProduct($order),
        );
    }

    public function testAQuantityBeyondWhatIsLeftIsRefused(): void
    {
        [$order, $first] = $this->paidOrderOfTwoLines(3.0, 2.0);
        $this->manager->open($order, [new ShipmentLine((int) $first->getId(), 2.0)]);

        $this->expectException(ShipmentNotAllowedException::class);
        $this->expectExceptionMessage('Only 1 left to ship');

        $this->manager->open($order, [new ShipmentLine((int) $first->getId(), 2.0)]);
    }

    public function testTheSameLineAskedTwiceInOneShipmentIsBoundedBySum(): void
    {
        [$order, $first] = $this->paidOrderOfTwoLines(3.0, 2.0);

        $this->expectException(ShipmentNotAllowedException::class);

        $this->manager->open($order, [
            new ShipmentLine((int) $first->getId(), 2.0),
            new ShipmentLine((int) $first->getId(), 2.0),
        ]);
    }

    public function testALineOfAnotherOrderOrOneThatDoesNotShipIsRefused(): void
    {
        [$order] = $this->paidOrderOfTwoLines(1.0, 1.0);
        $service = $this->factory->orderProduct($order, ['lineType' => OrderProduct::LINE_TYPE_SERVICE]);

        $this->expectException(ShipmentNotAllowedException::class);
        $this->expectExceptionMessage('is not a line of order');

        $this->manager->open($order, [new ShipmentLine((int) $service->getId(), 1.0)]);
    }

    public function testCancellingADraftGivesItsLinesBack(): void
    {
        [$order, $first] = $this->paidOrderOfTwoLines(3.0, 2.0);
        $draft = $this->manager->open($order, [new ShipmentLine((int) $first->getId(), 3.0)]);

        $this->manager->cancel($draft);

        self::assertTrue($draft->isCancelled());
        self::assertSame(3.0, $this->quantities->remainingByOrderProduct($order)[(int) $first->getId()]);

        // The rank is not reused.
        $next = $this->manager->open($order, [new ShipmentLine((int) $first->getId(), 3.0)]);
        self::assertSame($order->getRef().'-2', $next->getRef());
    }

    public function testAShipmentThatLeftCanNeitherLeaveAgainNorBeCancelled(): void
    {
        [$order, $first] = $this->paidOrderOfTwoLines(3.0, 2.0);
        $shipment = $this->manager->ship($this->manager->open($order, [new ShipmentLine((int) $first->getId(), 1.0)]));

        try {
            $this->manager->cancel($shipment);
            self::fail('A shipped parcel cannot be cancelled.');
        } catch (ShipmentNotAllowedException) {
        }

        $this->expectException(ShipmentNotAllowedException::class);
        $this->manager->ship($shipment);
    }

    public function testTheOrderIsPartiallyShippedThenSentAsItsParcelsLeave(): void
    {
        [$order, $first, $second] = $this->paidOrderOfTwoLines(3.0, 2.0);

        $firstParcel = $this->manager->ship(
            $this->manager->open($order, [new ShipmentLine((int) $first->getId(), 3.0)], deliveryRef: 'PARCEL-1'),
        );

        self::assertTrue($firstParcel->isShipped());
        self::assertNotNull($firstParcel->getShippedAt());
        self::assertSame(OrderStatus::CODE_PARTIALLY_SHIPPED, $this->statusCodeOf($order));
        self::assertSame('PARCEL-1', $this->reloaded($order)->getDeliveryRef(), 'The first parcel gives the order its tracking number.');

        $this->manager->ship(
            $this->manager->open($order, [new ShipmentLine((int) $second->getId(), 2.0)], deliveryRef: 'PARCEL-2'),
        );

        self::assertSame(OrderStatus::CODE_SENT, $this->statusCodeOf($order));
        self::assertSame('PARCEL-1', $this->reloaded($order)->getDeliveryRef(), 'The second parcel leaves the order number alone.');
        self::assertCount(2, OrderShipmentQuery::create()->filterByOrderId($order->getId())->filterByStatus(OrderShipment::STATUS_SHIPPED)->find());
    }

    public function testADraftKeptForLaterNeitherSendsTheOrderNorLeavesWithTheOtherParcel(): void
    {
        [$order, $first, $second] = $this->paidOrderOfTwoLines(1.0, 1.0);

        $parcel = $this->manager->open($order, [new ShipmentLine((int) $first->getId(), 1.0)]);
        $keptForLater = $this->manager->open($order, [new ShipmentLine((int) $second->getId(), 1.0)]);

        $this->manager->ship($parcel);

        self::assertSame(OrderShipment::STATUS_DRAFT, OrderShipmentQuery::create()->findPk($keptForLater->getId())?->getStatus(), 'The draft kept for later does not leave on its own.');
        self::assertSame(OrderStatus::CODE_PARTIALLY_SHIPPED, $this->statusCodeOf($order), 'One parcel of two left: the order is partially shipped, not sent.');
        self::assertFalse($this->quantities->isFullyShipped($order), 'A line waiting in a draft has not left.');
        self::assertSame([(int) $first->getId() => 0.0, (int) $second->getId() => 0.0], $this->quantities->remainingByOrderProduct($order), 'The draft still holds its line against what is left to ship.');
    }

    public function testTheNumberOfALaterParcelStaysOnThatParcel(): void
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $first = $this->factory->orderProduct($order, ['quantity' => 1.0]);
        $second = $this->factory->orderProduct($order, ['quantity' => 1.0]);
        $third = $this->factory->orderProduct($order, ['quantity' => 1.0]);

        $leftWithoutNumber = $this->manager->ship($this->manager->open($order, [new ShipmentLine((int) $first->getId(), 1.0)]));
        $draft = $this->manager->open($order, [new ShipmentLine((int) $second->getId(), 1.0)], deliveryRef: 'DRAFT-2');
        $parcel = $this->manager->ship($this->manager->open($order, [new ShipmentLine((int) $third->getId(), 1.0)], deliveryRef: 'PARCEL-3'));

        self::assertNull($this->reloadedShipment($leftWithoutNumber)->getDeliveryRef(), 'The first parcel left without a number and keeps none.');
        self::assertSame('DRAFT-2', $this->reloadedShipment($draft)->getDeliveryRef(), 'The number typed on a draft belongs to it.');
        self::assertSame('PARCEL-3', $this->reloadedShipment($parcel)->getDeliveryRef());
    }

    public function testNoParcelOfACancelledOrderIsOpened(): void
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_CANCELED]);
        $line = $this->factory->orderProduct($order, ['quantity' => 2.0]);

        try {
            $this->manager->open($order, [new ShipmentLine((int) $line->getId(), 1.0)]);
            self::fail('A parcel was opened on a cancelled order.');
        } catch (ShipmentNotAllowedException) {
        }

        self::assertSame(0, OrderShipmentQuery::create()->filterByOrderId($order->getId())->count());
        self::assertSame(OrderStatus::CODE_CANCELED, $this->statusCodeOf($order));
    }

    public function testADraftOpenedBeforeTheOrderWasRefundedDoesNotLeave(): void
    {
        [$order, $first] = $this->paidOrderOfTwoLines(2.0, 1.0);

        $draft = $this->manager->open($order, [new ShipmentLine((int) $first->getId(), 2.0)]);

        $order->setStatusId((int) OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_REFUNDED)?->getId())->save();

        try {
            $this->manager->ship($draft);
            self::fail('A draft of a refunded order left.');
        } catch (ShipmentNotAllowedException) {
        }

        self::assertTrue($this->reloadedShipment($draft)->isDraft());
        self::assertSame(OrderStatus::CODE_REFUNDED, $this->statusCodeOf($order));
    }

    public function testAnOrderMarkedSentByHandGetsOneShipmentCarryingEverythingLeft(): void
    {
        [$order, $first, $second] = $this->paidOrderOfTwoLines(3.0, 2.0);
        $this->manager->open($order, [new ShipmentLine((int) $first->getId(), 1.0)], deliveryRef: 'DRAFT-1');

        $orderEvent = new OrderEvent($order);
        $orderEvent->setDeliveryRef('BY-HAND');
        $this->dispatch($orderEvent, TheliaEvents::ORDER_UPDATE_DELIVERY_REF);

        $this->moveOrderTo($order, OrderStatus::CODE_SENT);

        $shipments = OrderShipmentQuery::create()->filterByOrderId($order->getId())->orderById()->find();
        self::assertCount(2, $shipments);

        [$draft, $rest] = $shipments->getData();
        self::assertTrue($draft->isShipped(), 'The open draft left with the order.');
        self::assertSame('BY-HAND', $draft->getDeliveryRef(), 'The number typed on the order is the number of its first parcel.');
        self::assertTrue($rest->isShipped());
        self::assertSame('BY-HAND', $rest->getDeliveryRef());
        self::assertSame(
            [(int) $first->getId() => 2.0, (int) $second->getId() => 2.0],
            $this->linesOf($rest),
        );
        self::assertTrue($this->quantities->isFullyShipped($order));
    }

    public function testAnOrderWithNothingToShipIsNeverFullyShipped(): void
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $this->factory->orderProduct($order, ['virtual' => 1]);

        self::assertSame([], $this->quantities->remainingByOrderProduct($order));
        self::assertFalse($this->quantities->isFullyShipped($order));
    }

    public function testEachGestureLeavesALineInTheOrderHistory(): void
    {
        [$order, $first] = $this->paidOrderOfTwoLines(3.0, 2.0);

        $cancelled = $this->manager->open($order, [new ShipmentLine((int) $first->getId(), 1.0)]);
        $this->manager->cancel($cancelled);
        $this->manager->ship($this->manager->open($order, [new ShipmentLine((int) $first->getId(), 1.0)], deliveryRef: 'TRACK'));

        // The invoice number allocated on entering a paid status is the order's own story.
        $types = array_values(array_filter(
            array_map(static fn ($entry) => $entry->getEventType(), OrderHistoryQuery::create()->filterByOrderId($order->getId())->orderById()->find()->getData()),
            static fn (string $type) => OrderHistoryEventType::INVOICE_REF_ALLOCATED->value !== $type,
        ));

        self::assertSame(
            [
                OrderHistoryEventType::SHIPMENT_OPENED->value,
                OrderHistoryEventType::SHIPMENT_CANCELLED->value,
                OrderHistoryEventType::SHIPMENT_OPENED->value,
                OrderHistoryEventType::SHIPMENT_SHIPPED->value,
                OrderHistoryEventType::DELIVERY_REF_UPDATED->value,
                OrderHistoryEventType::STATUS_CHANGED->value,
            ],
            $types,
        );

        $shipped = OrderHistoryQuery::create()->filterByOrderId($order->getId())->filterByEventType(OrderHistoryEventType::SHIPMENT_SHIPPED->value)->findOne();
        self::assertStringContainsString('"delivery_ref":"TRACK"', (string) $shipped?->getPayload());
    }

    /**
     * @return array{0: Order, 1: OrderProduct, 2: OrderProduct}
     */
    private function paidOrderOfTwoLines(float $firstQuantity, float $secondQuantity): array
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);

        return [
            $order,
            $this->factory->orderProduct($order, ['quantity' => $firstQuantity]),
            $this->factory->orderProduct($order, ['quantity' => $secondQuantity]),
        ];
    }

    private function statusCodeOf(Order $order): ?string
    {
        return OrderStatusQuery::create()->findPk($this->reloaded($order)->getStatusId())?->getCode();
    }

    private function reloaded(Order $order): Order
    {
        $order->reload();

        return $order;
    }

    private function reloadedShipment(OrderShipment $shipment): OrderShipment
    {
        $shipment->reload();

        return $shipment;
    }

    /**
     * @return array<int, float>
     */
    private function linesOf(OrderShipment $shipment): array
    {
        $lines = [];

        foreach ($shipment->getOrderShipmentItems() as $item) {
            $lines[(int) $item->getOrderProductId()] = (float) $item->getQuantity();
        }

        return $lines;
    }
}
