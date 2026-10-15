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

namespace Thelia\Domain\Shipping\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\OrderShipmentQuery;

/**
 * The tracking number typed on the order, from the back office or by a delivery
 * module that knows nothing of shipments, is the tracking number of its first
 * parcel: the shipment that stands for the order as it was before shipments existed.
 *
 * Runs after Thelia\Action\Order (128) wrote the order. Nothing is created here: an
 * order without a shipment gets one when it is marked sent, with that number on it.
 */
final readonly class SyncShipmentDeliveryRefListener
{
    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_DELIVERY_REF, priority: 64)]
    public function onDeliveryRefUpdated(OrderEvent $event): void
    {
        $deliveryRef = trim((string) $event->getDeliveryRef());

        if ('' === $deliveryRef) {
            return;
        }

        $first = OrderShipmentQuery::create()->findFirstParcelOfOrder($event->getOrder()->getId());

        if (null === $first || trim((string) $first->getDeliveryRef()) === $deliveryRef) {
            return;
        }

        $first->setDeliveryRef($deliveryRef)->save();
    }
}
