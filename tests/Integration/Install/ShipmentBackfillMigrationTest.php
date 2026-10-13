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

namespace Thelia\Tests\Integration\Install;

use Thelia\Model\Order;
use Thelia\Model\OrderShipment;
use Thelia\Model\OrderShipmentQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The statements 3.3.0.sql runs for the orders that left before shipments existed:
 * each is shown as one shipment carrying all its lines, and the partially shipped
 * status is seeded. A fresh install seeds the status too.
 */
final class ShipmentBackfillMigrationTest extends IntegrationTestCase
{
    public function testAFreshInstallSeedsThePartiallyShippedStatusEquivalentToPaid(): void
    {
        $status = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PARTIALLY_SHIPPED);

        self::assertNotNull($status);
        self::assertSame(OrderStatus::CODE_PAID, $status->getEquivalentCode());
        self::assertSame(0, (int) $status->getProtectedStatus());
        self::assertSame('Partially shipped', $status->setLocale('en_US')->getTitle());
        self::assertSame('Partiellement expédiée', $status->setLocale('fr_FR')->getTitle());
    }

    public function testASentOrderGetsOneShippedShipmentCarryingAllItsLines(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $order->setDeliveryRef('OLD-TRACKING')->save($this->getPropelConnection());
        $first = $factory->orderProduct($order, ['quantity' => 2.0]);
        $second = $factory->orderProduct($order, ['quantity' => 1.0]);

        $this->runBackfill();

        $shipment = $this->theOnlyShipmentOf($order);
        self::assertSame(OrderShipment::STATUS_SHIPPED, $shipment->getStatus());
        self::assertSame($order->getRef().'-1', $shipment->getRef());
        self::assertSame('OLD-TRACKING', $shipment->getDeliveryRef());
        self::assertSame($order->getDeliveryModuleId(), $shipment->getDeliveryModuleId());
        self::assertNotNull($shipment->getShippedAt());
        self::assertSame(
            [(int) $first->getId() => 2.0, (int) $second->getId() => 1.0],
            $this->linesOf($shipment),
        );
    }

    public function testAnOrderInAStatusEquivalentToSentIsShippedToo(): void
    {
        $factory = $this->createFixtureFactory();
        $delivered = $factory->orderStatus(['code' => 'delivered_to_customer', 'equivalentCode' => OrderStatus::CODE_SENT]);
        $order = $factory->order(null, ['statusCode' => 'delivered_to_customer']);
        $factory->orderProduct($order);

        $this->runBackfill();

        self::assertSame(OrderShipment::STATUS_SHIPPED, $this->theOnlyShipmentOf($order)->getStatus());
        self::assertSame($delivered->getId(), $order->getStatusId());
    }

    public function testAPaidOrderThatOnlyCarriesATrackingNumberGetsADraft(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $order->setDeliveryRef('LABEL-PRINTED')->save($this->getPropelConnection());
        $factory->orderProduct($order);

        $this->runBackfill();

        $shipment = $this->theOnlyShipmentOf($order);
        self::assertSame(OrderShipment::STATUS_DRAFT, $shipment->getStatus());
        self::assertSame('LABEL-PRINTED', $shipment->getDeliveryRef());
        self::assertNull($shipment->getShippedAt());
    }

    public function testAPaidOrderWithoutATrackingNumberIsLeftAlone(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $factory->orderProduct($order);

        $this->runBackfill();

        self::assertCount(0, OrderShipmentQuery::create()->filterByOrderId($order->getId())->find());
    }

    public function testTheBackfillReplaysWithoutDoublingAnything(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $factory->orderProduct($order);

        $this->runBackfill();
        $this->runBackfill();

        $shipment = $this->theOnlyShipmentOf($order);
        self::assertCount(1, $shipment->getOrderShipmentItems());
        self::assertCount(1, OrderStatusQuery::create()->filterByCode(OrderStatus::CODE_PARTIALLY_SHIPPED)->find());
    }

    public function testAnOrderThatAlreadyHasAShipmentIsLeftAlone(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $line = $factory->orderProduct($order, ['quantity' => 3.0]);
        $existing = (new OrderShipment())
            ->setOrderId((int) $order->getId())
            ->setRef($order->getRef().'-1')
            ->setStatus(OrderShipment::STATUS_SHIPPED);
        $existing->save($this->getPropelConnection());
        $this->getPropelConnection()->exec(\sprintf(
            'INSERT INTO `order_shipment_item` (`order_shipment_id`, `order_product_id`, `quantity`, `created_at`, `updated_at`) VALUES (%d, %d, 1, NOW(), NOW())',
            $existing->getId(),
            $line->getId(),
        ));

        $this->runBackfill();

        $shipment = $this->theOnlyShipmentOf($order);
        self::assertSame($existing->getId(), $shipment->getId());
        self::assertSame([(int) $line->getId() => 1.0], $this->linesOf($shipment), 'A shipment that has lines keeps them as they are.');
    }

    private function runBackfill(): void
    {
        foreach ($this->backfillStatements() as $statement) {
            $this->getPropelConnection()->exec($statement);
        }
    }

    /**
     * The status seed and the two backfill inserts of the script, the table creation
     * left out: the test database is built from the schema.
     *
     * @return list<string>
     */
    private function backfillStatements(): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.3.0.sql');

        $statements = [];

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (str_contains($sql, 'partially_shipped') || str_contains($sql, 'INSERT IGNORE INTO `order_shipment')) {
                $statements[] = $sql;
            }
        }

        self::assertCount(5, $statements, 'The 3.3.0 script does not seed the shipments as expected.');

        return $statements;
    }

    private function theOnlyShipmentOf(Order $order): OrderShipment
    {
        $shipments = OrderShipmentQuery::create()->filterByOrderId($order->getId())->find();
        self::assertCount(1, $shipments);

        return $shipments->getFirst();
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
