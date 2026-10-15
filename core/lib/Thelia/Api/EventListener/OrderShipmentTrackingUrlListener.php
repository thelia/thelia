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

namespace Thelia\Api\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\Resource\Order as OrderResource;
use Thelia\Api\Resource\OrderShipment as OrderShipmentResource;
use Thelia\Domain\Order\Service\OrderTrackingUrlResolver;
use Thelia\Model\Order;
use Thelia\Model\OrderShipment;

/**
 * Gives every shipment read the carrier page following its parcel, whether it is read
 * on its own or through its order. Ownership is already enforced by the operation that
 * reads it: this only adds a computed field.
 */
final readonly class OrderShipmentTrackingUrlListener implements EventSubscriberInterface
{
    public function __construct(private OrderTrackingUrlResolver $trackingUrlResolver)
    {
    }

    public function addTrackingUrl(ModelToResourceEvent $event): void
    {
        $resource = $event->getResource();
        $model = $event->getModel();

        if ($resource instanceof OrderShipmentResource && $model instanceof OrderShipment) {
            $resource->setTrackingUrl($this->trackingUrlResolver->resolveShipment($model));

            return;
        }

        // The shipments read through their order: the event of a nested resource carries
        // the root model, so the parcels of an order are given their page from here.
        if ($resource instanceof OrderResource && $model instanceof Order) {
            $this->addTrackingUrlToTheParcelsOf($resource, $model);
        }
    }

    private function addTrackingUrlToTheParcelsOf(OrderResource $order, Order $model): void
    {
        $parcels = $order->getOrderShipments();

        if ([] === $parcels) {
            return;
        }

        $modelsById = [];

        foreach ($model->getOrderShipments() as $shipment) {
            $modelsById[(int) $shipment->getId()] = $shipment;
        }

        foreach ($parcels as $parcel) {
            if (!$parcel instanceof OrderShipmentResource) {
                continue;
            }

            $shipment = $modelsById[(int) $parcel->getId()] ?? null;

            if ($shipment instanceof OrderShipment) {
                $parcel->setTrackingUrl($this->trackingUrlResolver->resolveShipment($shipment));
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ModelToResourceEvent::AFTER_TRANSFORM => [
                ['addTrackingUrl', 0],
            ],
        ];
    }
}
