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
use Thelia\Core\Event\Order\OrderShipmentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Model\OrderShipment;

/**
 * Writes what happens to a shipment in the history of its order: opened, shipped,
 * cancelled. Codes and references only, the tracking number included, since it is
 * what the merchant looks for when a customer calls about a parcel.
 */
final readonly class RecordOrderShipmentHistoryListener
{
    public function __construct(
        private OrderHistoryRecorder $orderHistoryRecorder,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_SHIPMENT_CREATE, priority: 64)]
    public function onShipmentOpened(OrderShipmentEvent $event): void
    {
        $this->record($event->getOrderShipment(), OrderHistoryEventType::SHIPMENT_OPENED);
    }

    #[AsEventListener(event: TheliaEvents::ORDER_SHIPMENT_SHIP, priority: 64)]
    public function onShipmentShipped(OrderShipmentEvent $event): void
    {
        $this->record($event->getOrderShipment(), OrderHistoryEventType::SHIPMENT_SHIPPED);
    }

    #[AsEventListener(event: TheliaEvents::ORDER_SHIPMENT_CANCEL, priority: 64)]
    public function onShipmentCancelled(OrderShipmentEvent $event): void
    {
        $this->record($event->getOrderShipment(), OrderHistoryEventType::SHIPMENT_CANCELLED);
    }

    private function record(OrderShipment $shipment, OrderHistoryEventType $type): void
    {
        $deliveryRef = trim((string) $shipment->getDeliveryRef());

        $this->orderHistoryRecorder->record(
            (int) $shipment->getOrderId(),
            $type->value,
            [
                'shipment_id' => (int) $shipment->getId(),
                'shipment_ref' => (string) $shipment->getRef(),
                'delivery_ref' => '' === $deliveryRef ? null : $deliveryRef,
                'carrier' => $shipment->getDeliveryModuleTitle(),
                'lines' => $shipment->getOrderShipmentItems()->count(),
            ],
        );
    }
}
