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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\ShippingZone\ShippingZoneAddAreaEvent;
use Thelia\Core\Event\ShippingZone\ShippingZoneRemoveAreaEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Event\AreaEvent;
use Thelia\Model\ModuleQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class ShippingZoneActionTest extends ActionIntegrationTestCase
{
    public function testAddAreaLinksAnAreaToADeliveryModule(): void
    {
        [$area, $deliveryModuleId] = $this->persistAreaAndDeliveryModuleId('Europe');

        $this->dispatch(
            new ShippingZoneAddAreaEvent($area->getId(), $deliveryModuleId),
            TheliaEvents::SHIPPING_ZONE_ADD_AREA,
        );

        $link = AreaDeliveryModuleQuery::create()
            ->filterByAreaId($area->getId())
            ->filterByDeliveryModuleId($deliveryModuleId)
            ->count();
        self::assertSame(1, $link);
    }

    public function testRemoveAreaDetachesAreaFromDeliveryModule(): void
    {
        [$area, $deliveryModuleId] = $this->persistAreaAndDeliveryModuleId('Americas');

        $this->dispatch(
            new ShippingZoneAddAreaEvent($area->getId(), $deliveryModuleId),
            TheliaEvents::SHIPPING_ZONE_ADD_AREA,
        );

        $this->dispatch(
            new ShippingZoneRemoveAreaEvent($area->getId(), $deliveryModuleId),
            TheliaEvents::SHIPPING_ZONE_REMOVE_AREA,
        );

        $remaining = AreaDeliveryModuleQuery::create()
            ->filterByAreaId($area->getId())
            ->filterByDeliveryModuleId($deliveryModuleId)
            ->count();
        self::assertSame(0, $remaining);
    }

    public function testRemoveAreaThrowsWhenLinkIsUnknown(): void
    {
        [$area, $deliveryModuleId] = $this->persistAreaAndDeliveryModuleId('Asia');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('areaDeliveryModule not found');

        $this->dispatch(
            new ShippingZoneRemoveAreaEvent($area->getId(), $deliveryModuleId),
            TheliaEvents::SHIPPING_ZONE_REMOVE_AREA,
        );
    }

    /**
     * @return array{Area, int} the freshly persisted area, and the id of an
     *                          installed delivery module (`CustomDelivery`,
     *                          seeded by bin/test-prepare)
     */
    private function persistAreaAndDeliveryModuleId(string $name): array
    {
        $area = (new Area())->setName($name);
        $this->dispatch(new AreaEvent($area), TheliaEvents::AREA_CREATE);

        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery');
        self::assertNotNull($deliveryModule, 'CustomDelivery module must be registered by bin/test-prepare');

        return [$area, $deliveryModule->getId()];
    }
}
