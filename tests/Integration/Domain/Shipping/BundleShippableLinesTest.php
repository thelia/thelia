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

use Thelia\Domain\Shipping\DTO\ShipmentLine;
use Thelia\Domain\Shipping\Exception\ShipmentNotAllowedException;
use Thelia\Domain\Shipping\Service\OrderShipmentManager;
use Thelia\Domain\Shipping\Service\ShippedQuantityCalculator;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatus;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * What leaves in a parcel when an order holds a pack: the components, never the line of the
 * pack, which stands for them and would otherwise wait to be shipped for ever.
 */
final class BundleShippableLinesTest extends ActionIntegrationTestCase
{
    public function testOnlyTheComponentsOfABundleAreLeftToShip(): void
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $bundleLine = $this->factory->orderProduct($order, ['lineType' => OrderProduct::LINE_TYPE_BUNDLE, 'quantity' => 2.0, 'price' => '540.000000']);
        $bike = $this->factory->orderProduct($order, ['lineType' => OrderProduct::LINE_TYPE_BUNDLE_COMPONENT, 'quantity' => 2.0, 'price' => '0']);
        $helmet = $this->factory->orderProduct($order, ['lineType' => OrderProduct::LINE_TYPE_BUNDLE_COMPONENT, 'quantity' => 4.0, 'price' => '0']);
        $alone = $this->factory->orderProduct($order, ['quantity' => 1.0]);

        $remaining = $this->getService(ShippedQuantityCalculator::class)->remainingByOrderProduct($order);

        self::assertSame(
            [(int) $bike->getId() => 2.0, (int) $helmet->getId() => 4.0, (int) $alone->getId() => 1.0],
            $remaining,
            'The line of the pack is not something to pick.',
        );
        self::assertArrayNotHasKey((int) $bundleLine->getId(), $remaining);
    }

    public function testTheLineOfABundleCannotBePutInAParcel(): void
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $bundleLine = $this->factory->orderProduct($order, ['lineType' => OrderProduct::LINE_TYPE_BUNDLE]);
        $this->factory->orderProduct($order, ['lineType' => OrderProduct::LINE_TYPE_BUNDLE_COMPONENT]);

        $this->expectException(ShipmentNotAllowedException::class);

        $this->getService(OrderShipmentManager::class)->open($order, [new ShipmentLine((int) $bundleLine->getId(), 1.0)]);
    }

    public function testAnOrderIsFullyShippedWhenItsComponentsLeft(): void
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $this->factory->orderProduct($order, ['lineType' => OrderProduct::LINE_TYPE_BUNDLE, 'quantity' => 1.0]);
        $bike = $this->factory->orderProduct($order, ['lineType' => OrderProduct::LINE_TYPE_BUNDLE_COMPONENT, 'quantity' => 1.0]);
        $manager = $this->getService(OrderShipmentManager::class);
        $calculator = $this->getService(ShippedQuantityCalculator::class);

        self::assertFalse($calculator->isFullyShipped($order));

        $shipment = $manager->open($order, [new ShipmentLine((int) $bike->getId(), 1.0)]);
        $manager->ship($shipment, 'TRACK-1');

        self::assertTrue($calculator->isFullyShipped($order), 'The line of the pack is not left over once its components left.');
    }
}
