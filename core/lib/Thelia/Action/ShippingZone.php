<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\ShippingZone\ShippingZoneAddAreaEvent;
use Thelia\Core\Event\ShippingZone\ShippingZoneRemoveAreaEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\AreaDeliveryModuleQuery;

/**
 * Class ShippingZone.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ShippingZone extends BaseAction implements EventSubscriberInterface
{
    public function addArea(ShippingZoneAddAreaEvent $event): void
    {
        $areaDelivery = new AreaDeliveryModule();

        $areaDelivery
            ->setAreaId($event->getAreaId())
            ->setDeliveryModuleId($event->getShippingZoneId())
            ->save();
    }

    public function removeArea(ShippingZoneRemoveAreaEvent $event): void
    {
        $areaDelivery = AreaDeliveryModuleQuery::create()
            ->filterByAreaId($event->getAreaId())
            ->filterByDeliveryModuleId($event->getShippingZoneId())
            ->findOne();

        if ($areaDelivery) {
            $areaDelivery->delete();
        } else {
            throw new \RuntimeException(sprintf('areaDeliveryModule not found with area_id = %d and delivery_module_id = %d', $event->getAreaId(), $event->getShippingZoneId()));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::SHIPPING_ZONE_ADD_AREA => ['addArea', 128],
            TheliaEvents::SHIPPING_ZONE_REMOVE_AREA => ['removeArea', 128],
        ];
    }
}
