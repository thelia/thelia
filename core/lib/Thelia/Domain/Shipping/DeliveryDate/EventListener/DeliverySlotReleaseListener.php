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

namespace Thelia\Domain\Shipping\DeliveryDate\EventListener;

use Propel\Runtime\Propel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliverySlotBooker;
use Thelia\Model\Map\DeliverySlotBookingTableMap;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * Gives the place in its delivery slot back when an order is cancelled or refunded, and
 * takes it again when such an order comes back to life.
 *
 * Without it, a cancelled order would hold its slot for good and the merchant would see a
 * slot full of orders that will never be delivered. Coming back takes the place whatever the
 * capacity says: the order already has its day, and the count has to say so.
 *
 * Runs after the status was written (the core action is at 128).
 */
final readonly class DeliverySlotReleaseListener implements EventSubscriberInterface
{
    public function __construct(
        private DeliverySlotBooker $booker,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::ORDER_UPDATE_STATUS => ['onStatusChange', 64],
        ];
    }

    public function onStatusChange(OrderEvent $event): void
    {
        $order = $event->getOrder();
        $slotId = $order->getDeliverySlotId();
        $date = $order->getDeliveryDate('Y-m-d');

        if (null === $slotId || null === $date || null === $event->getPreviousStatusId()) {
            return;
        }

        $heldBefore = $this->holdsItsSlot(OrderStatusQuery::create()->findPk($event->getPreviousStatusId()));
        $heldNow = $this->holdsItsSlot(OrderStatusQuery::create()->findPk($order->getStatusId()));

        if ($heldBefore === $heldNow) {
            return;
        }

        $connection = Propel::getWriteConnection(DeliverySlotBookingTableMap::DATABASE_NAME);

        // The status is already written: a failure here must not undo it, and must not go
        // unseen either, or the slot keeps a place nobody holds.
        try {
            if ($heldNow) {
                $this->booker->restore((int) $slotId, (string) $date, $connection);
            } else {
                $this->booker->release((int) $slotId, (string) $date, $connection);
            }
        } catch (\Throwable $failure) {
            $this->logger->error('The delivery slot place of an order could not be updated after its status changed.', [
                'order_id' => $order->getId(),
                'delivery_slot_id' => (int) $slotId,
                'delivery_date' => (string) $date,
                'operation' => $heldNow ? 'restore' : 'release',
                'exception' => $failure,
            ]);
        }
    }

    private function holdsItsSlot(?OrderStatus $status): bool
    {
        return null !== $status && !$status->isCancelled() && !$status->isRefunded();
    }
}
