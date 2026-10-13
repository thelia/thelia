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

namespace Thelia\Tests\Integration\Core\Template;

use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderShipment;
use Thelia\Model\OrderShipmentItem;
use Thelia\Model\OrderStatus;
use Thelia\Test\IntegrationTestCase;

/**
 * The order lines of one shipment, and the shipments of an order, as templates read them.
 */
final class OrderShipmentLoopsTest extends IntegrationTestCase
{
    public function testTheLinesOfAShipmentCarryItsQuantityAndTheTotalsOfThatQuantity(): void
    {
        [$order, $widget, $gadget, $first, $second] = $this->orderShippedInTwoParcels();

        $rows = array_column($this->rows('order_product', ['order' => $order->getId(), 'shipment' => $first->getId()]), null, 'ID');
        $line = $rows[$widget->getId()];

        // Two tax rows on the line, 1.00 and 0.50 a unit, and the line in two shipments:
        // the taxes are summed once, whatever the number of shipments carrying the line.
        self::assertEqualsWithDelta(1.5, (float) $line['PRICE_TAX'], 0.0001);
        self::assertEqualsWithDelta(2.0, (float) $line['QUANTITY'], 0.0001);
        self::assertEqualsWithDelta(20.0, (float) $line['TOTAL_PRICE'], 0.0001);
        self::assertEqualsWithDelta(23.0, (float) $line['TOTAL_TAXED_PRICE'], 0.0001);
        self::assertEqualsWithDelta(3.0, (float) $line['REAL_TOTAL_PRICE_TAX'], 0.0001);
        self::assertSame([(int) $widget->getId()], array_keys($rows));

        $rows = $this->rows('order_product', ['order' => $order->getId(), 'shipment' => $second->getId()]);
        $byId = array_column($rows, null, 'ID');

        self::assertCount(2, $rows);
        self::assertEqualsWithDelta(1.0, (float) $byId[$widget->getId()]['QUANTITY'], 0.0001);
        self::assertEqualsWithDelta(11.5, (float) $byId[$widget->getId()]['TOTAL_TAXED_PRICE'], 0.0001);
        self::assertEqualsWithDelta(4.0, (float) $byId[$gadget->getId()]['QUANTITY'], 0.0001);
        self::assertEqualsWithDelta(46.0, (float) $byId[$gadget->getId()]['TOTAL_TAXED_PRICE'], 0.0001);
    }

    public function testTheLinesOfTheOrderAreUnchangedWithoutAShipment(): void
    {
        [$order, $widget, $gadget] = $this->orderShippedInTwoParcels();

        $byId = array_column($this->rows('order_product', ['order' => $order->getId()]), null, 'ID');

        self::assertCount(2, $byId);
        self::assertEqualsWithDelta(3.0, (float) $byId[$widget->getId()]['QUANTITY'], 0.0001);
        self::assertEqualsWithDelta(1.5, (float) $byId[$widget->getId()]['PRICE_TAX'], 0.0001);
        self::assertEqualsWithDelta(34.5, (float) $byId[$widget->getId()]['TOTAL_TAXED_PRICE'], 0.0001);
        self::assertEqualsWithDelta(4.0, (float) $byId[$gadget->getId()]['QUANTITY'], 0.0001);
    }

    public function testTheShipmentsOfAnOrderAreListedWithTheirNumberOfLines(): void
    {
        [$order, , , $first, $second] = $this->orderShippedInTwoParcels();

        $rows = $this->rows('order_shipment', ['order' => $order->getId()]);

        self::assertSame([(int) $first->getId(), (int) $second->getId()], array_map(static fn (array $row): int => (int) $row['ID'], $rows));
        self::assertSame($first->getRef(), $rows[0]['REF']);
        self::assertSame('TRACK-1', $rows[0]['DELIVERY_REF']);
        self::assertSame(1, $rows[0]['ITEM_COUNT']);
        self::assertSame(2, $rows[1]['ITEM_COUNT']);
        self::assertSame((int) $order->getId(), (int) $rows[0]['ORDER_ID']);

        $drafts = $this->rows('order_shipment', ['order' => $order->getId(), 'status' => OrderShipment::STATUS_DRAFT]);

        self::assertSame([(int) $second->getId()], array_map(static fn (array $row): int => (int) $row['ID'], $drafts));
    }

    /**
     * @return array{Order, OrderProduct, OrderProduct, OrderShipment, OrderShipment}
     */
    private function orderShippedInTwoParcels(): array
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();
        $order = $factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $widget = $factory->orderProduct($order, ['quantity' => 3.0, 'title' => 'Widget', 'productRef' => 'WIDGET']);
        $gadget = $factory->orderProduct($order, ['quantity' => 4.0, 'title' => 'Gadget', 'productRef' => 'GADGET']);

        foreach ([$widget, $gadget] as $line) {
            foreach (['1.000000', '0.500000'] as $i => $amount) {
                (new OrderProductTax())
                    ->setOrderProductId((int) $line->getId())
                    ->setTitle('Tax '.$i)
                    ->setAmount($amount)
                    ->setPromoAmount($amount)
                    ->save($connection);
            }
        }

        $first = $this->shipment($order, 1, OrderShipment::STATUS_SHIPPED, [[$widget, 2.0]]);
        $second = $this->shipment($order, 2, OrderShipment::STATUS_DRAFT, [[$widget, 1.0], [$gadget, 4.0]]);

        return [$order, $widget, $gadget, $first, $second];
    }

    /**
     * @param list<array{OrderProduct, float}> $lines
     */
    private function shipment(Order $order, int $rank, string $status, array $lines): OrderShipment
    {
        $connection = $this->getPropelConnection();

        $shipment = (new OrderShipment())
            ->setOrderId((int) $order->getId())
            ->setRef('SHIPMENT-'.$order->getId().'-'.$rank)
            ->setStatus($status)
            ->setDeliveryRef('TRACK-'.$rank);
        $shipment->save($connection);

        foreach ($lines as [$line, $quantity]) {
            (new OrderShipmentItem())
                ->setOrderShipmentId((int) $shipment->getId())
                ->setOrderProductId((int) $line->getId())
                ->setQuantity($quantity)
                ->save($connection);
        }

        return $shipment;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $loop, array $arguments): array
    {
        $rows = [];
        foreach ($this->getService(LoopExecutor::class)->execute($loop, $arguments) as $row) {
            $rows[] = $row->getVarVal();
        }

        return $rows;
    }
}
