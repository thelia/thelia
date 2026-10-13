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
use Thelia\Api\Resource\OrderShipment as OrderShipmentResource;
use Thelia\Domain\Order\Service\OrderTrackingUrlResolver;
use Thelia\Model\OrderShipment;

/**
 * Gives every shipment read the carrier page following its parcel. Ownership is
 * already enforced by the operation that reads it, or by the order it is read
 * through: this only adds a computed field.
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

        if (!$resource instanceof OrderShipmentResource || !$model instanceof OrderShipment) {
            return;
        }

        $resource->setTrackingUrl($this->trackingUrlResolver->resolveShipment($model));
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
