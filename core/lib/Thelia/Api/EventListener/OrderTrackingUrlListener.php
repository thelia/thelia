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
use Thelia\Domain\Order\Service\OrderTrackingUrlResolver;
use Thelia\Model\Order;

/**
 * Gives a single order read, by its owner or an administrator, the carrier page
 * following its parcel. Ownership is already enforced by the operation: this only
 * adds a computed field to an order the reader is allowed to see.
 */
final readonly class OrderTrackingUrlListener implements EventSubscriberInterface
{
    public function __construct(private OrderTrackingUrlResolver $trackingUrlResolver)
    {
    }

    public function addTrackingUrl(ModelToResourceEvent $event): void
    {
        $resource = $event->getResource();
        $model = $event->getModel();

        if (!$resource instanceof OrderResource || !$model instanceof Order || !self::readsASingleOrder($event->getContext())) {
            return;
        }

        $resource->setDeliveryTrackingUrl($this->trackingUrlResolver->resolve($model));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ModelToResourceEvent::AFTER_TRANSFORM => [
                ['addTrackingUrl', 0],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function readsASingleOrder(array $context): bool
    {
        $groups = $context['groups'] ?? [];
        $groups = \is_array($groups) ? $groups : [$groups];

        return \in_array(OrderResource::GROUP_FRONT_READ_SINGLE, $groups, true)
            || \in_array(OrderResource::GROUP_ADMIN_READ_SINGLE, $groups, true);
    }
}
