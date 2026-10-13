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
use Thelia\Domain\Order\Service\OrderStatusCatalog;
use Thelia\Domain\Shipping\Service\OrderShipmentManager;
use Thelia\Model\OrderStatus;

/**
 * An order entering a sent status has left entirely, whoever says so: the back
 * office, a delivery module printing its label, a command. Whatever was still to
 * ship leaves then, as one shipment carrying the order's tracking number, and the
 * drafts still open leave with it. So every sent order has its shipments, and the
 * customer account, the order sheet and the delivery notes read the same for an
 * order shipped the old way and one composed parcel by parcel.
 *
 * Runs after Thelia\Action\Order (128) committed the status and after the history
 * (64) wrote the change; the shipments it creates write their own lines after it.
 * An order shipped parcel by parcel reaches sent with nothing left, and this does
 * nothing then.
 */
final readonly class ShipEverythingOnSentListener
{
    public function __construct(
        private OrderShipmentManager $shipmentManager,
        private OrderStatusCatalog $statusCatalog,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 48)]
    public function onOrderStatusUpdate(OrderEvent $event): void
    {
        if (!$this->entersTheSentStatus($event)) {
            return;
        }

        $this->shipmentManager->shipEverythingLeft($event->getOrder());
    }

    private function entersTheSentStatus(OrderEvent $event): bool
    {
        $previousStatusId = $event->getPreviousStatusId();
        $newStatusId = $event->getStatus();

        if (null === $previousStatusId || null === $newStatusId || $previousStatusId === $newStatusId) {
            return false;
        }

        if (OrderStatus::CODE_SENT !== $this->statusCatalog->get($newStatusId)?->getEffectiveCode()) {
            return false;
        }

        return OrderStatus::CODE_SENT !== $this->statusCatalog->get($previousStatusId)?->getEffectiveCode();
    }
}
