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

namespace Thelia\Domain\Shipping\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderShipmentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Service\OrderStatusCatalog;
use Thelia\Domain\Shipping\DTO\ShipmentLine;
use Thelia\Domain\Shipping\Exception\ShipmentNotAllowedException;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\OrderShipmentTableMap;
use Thelia\Model\ModuleI18nQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderShipment;
use Thelia\Model\OrderShipmentItem;
use Thelia\Model\OrderShipmentQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * The one writer of shipments: opens a draft from the lines the merchant picked,
 * ships it, cancels it, and moves the order along as its parcels leave.
 *
 * Each gesture runs in a transaction that first locks the order row, so two
 * shipments composed at the same instant are checked one after the other against
 * what is left to ship, and the sum of what left can never exceed what was ordered.
 * The events are dispatched once the transaction is committed: they announce what
 * happened, and a listener that fails cannot undo it.
 *
 * The order keeps carrying the tracking number of its first parcel in
 * `delivery_ref`, which every delivery module published so far reads and writes:
 * shipping the first parcel fills it when it is empty, through the same event the
 * back office dispatches, so the history and the modules see one gesture.
 */
final readonly class OrderShipmentManager
{
    public function __construct(
        private ShippedQuantityCalculator $quantities,
        private OrderStatusCatalog $statusCatalog,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Opens a draft shipment carrying the given lines, each bounded by what is left
     * to ship on it. The carrier and the tracking number default to the order's.
     *
     * @param list<ShipmentLine> $lines
     *
     * @throws ShipmentNotAllowedException
     */
    public function open(Order $order, array $lines, ?int $deliveryModuleId = null, ?string $deliveryRef = null): OrderShipment
    {
        $wanted = self::mergeLines($lines);

        $connection = Propel::getWriteConnection(OrderShipmentTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->assertOrderShips($order, $this->lockOrder($order, $connection));
            $this->assertWithinRemaining($order, $wanted, $connection);

            $moduleId = $deliveryModuleId ?? $order->getDeliveryModuleId();

            $shipment = (new OrderShipment())
                ->setOrderId((int) $order->getId())
                ->setRef($this->nextRef($order, $connection))
                ->setStatus(OrderShipment::STATUS_DRAFT)
                ->setDeliveryModuleId($moduleId)
                ->setDeliveryModuleTitle($this->carrierTitle($moduleId, $order))
                ->setDeliveryRef(self::cleanRef($deliveryRef));
            $shipment->save($connection);

            foreach ($wanted as $orderProductId => $quantity) {
                (new OrderShipmentItem())
                    ->setOrderShipmentId((int) $shipment->getId())
                    ->setOrderProductId($orderProductId)
                    ->setQuantity($quantity)
                    ->save($connection);
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        $this->dispatcher->dispatch(new OrderShipmentEvent($shipment), TheliaEvents::ORDER_SHIPMENT_CREATE);

        return $shipment;
    }

    /**
     * Hands a draft to the carrier. A tracking number given here replaces the one
     * typed when the draft was opened. Unless told otherwise, the order follows: sent
     * once nothing is left to ship, partially shipped before that when the shop has
     * that status.
     *
     * @throws ShipmentNotAllowedException
     */
    public function ship(OrderShipment $shipment, ?string $deliveryRef = null, bool $settleOrderStatus = true): OrderShipment
    {
        if (!$shipment->isDraft()) {
            throw new ShipmentNotAllowedException(\sprintf('Shipment %s already left or was cancelled.', $shipment->getRef()));
        }

        $order = $shipment->getOrder();

        $connection = Propel::getWriteConnection(OrderShipmentTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $orderStatusId = $this->lockOrder($order, $connection);

            // Reloaded under the lock: the draft may have been shipped or cancelled by
            // the request that held the lock before this one.
            $shipment->reload(false, $connection);

            if (!$shipment->isDraft()) {
                throw new ShipmentNotAllowedException(\sprintf('Shipment %s already left or was cancelled.', $shipment->getRef()));
            }

            // A draft opened before the order was cancelled or refunded stays a draft.
            $this->assertOrderShips($order, $orderStatusId);

            if (null !== $deliveryRef) {
                $shipment->setDeliveryRef(self::cleanRef($deliveryRef));
            }

            $shipment
                ->setStatus(OrderShipment::STATUS_SHIPPED)
                ->setShippedAt(new \DateTime())
                ->save($connection);

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        $this->dispatcher->dispatch(new OrderShipmentEvent($shipment), TheliaEvents::ORDER_SHIPMENT_SHIP);

        $trackingNumber = trim((string) $shipment->getDeliveryRef());

        // Only the first parcel speaks for the order: the delivery reference event writes
        // the number on the first parcel, so the number of another one would land there.
        if ('' !== $trackingNumber && '' === trim((string) $order->getDeliveryRef()) && $this->isFirstParcel($shipment)) {
            $orderEvent = new OrderEvent($order);
            $orderEvent->setDeliveryRef($trackingNumber);
            $this->dispatcher->dispatch($orderEvent, TheliaEvents::ORDER_UPDATE_DELIVERY_REF);
        }

        if ($settleOrderStatus) {
            $this->settleOrderStatus($order);
        }

        return $shipment;
    }

    /**
     * Gives up a draft before it leaves: its lines are back in what is left to ship.
     *
     * @throws ShipmentNotAllowedException
     */
    public function cancel(OrderShipment $shipment): OrderShipment
    {
        if (!$shipment->isDraft()) {
            throw new ShipmentNotAllowedException(\sprintf('Shipment %s already left or was cancelled.', $shipment->getRef()));
        }

        $connection = Propel::getWriteConnection(OrderShipmentTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->lockOrder($shipment->getOrder(), $connection);
            $shipment->reload(false, $connection);

            if (!$shipment->isDraft()) {
                throw new ShipmentNotAllowedException(\sprintf('Shipment %s already left or was cancelled.', $shipment->getRef()));
            }

            $shipment->setStatus(OrderShipment::STATUS_CANCELLED)->save($connection);

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        $this->dispatcher->dispatch(new OrderShipmentEvent($shipment), TheliaEvents::ORDER_SHIPMENT_CANCEL);

        return $shipment;
    }

    /**
     * Ships every draft of the order, then puts whatever is still left to ship into
     * one more shipment that leaves at once, with the carrier and tracking number of
     * the order. What an order marked sent by hand, or by a delivery module that
     * knows nothing of shipments, needs so that it reads as shipped everywhere.
     *
     * The order status is left alone: this is called while it changes.
     *
     * @return list<OrderShipment> the shipments that left
     */
    public function shipEverythingLeft(Order $order): array
    {
        $shipped = [];

        foreach (OrderShipmentQuery::create()->filterByOrderId($order->getId())->filterByStatus(OrderShipment::STATUS_DRAFT)->orderById()->find() as $draft) {
            $shipped[] = $this->ship($draft, settleOrderStatus: false);
        }

        $lines = [];

        foreach ($this->quantities->remainingByOrderProduct($order) as $orderProductId => $left) {
            if ($left > 0.0) {
                $lines[] = new ShipmentLine($orderProductId, $left);
            }
        }

        if ([] !== $lines) {
            $shipped[] = $this->ship($this->open($order, $lines, deliveryRef: $order->getDeliveryRef()), settleOrderStatus: false);
        }

        return $shipped;
    }

    /**
     * Moves the order to sent once nothing is left to ship, or to the partially
     * shipped status when the shop has one and the order is not there yet. Goes
     * through the status event, so the transition graph, the stock, the history and
     * the shipping e-mail all answer as for a change made from the back office.
     */
    private function settleOrderStatus(Order $order): void
    {
        $order->reload();
        $currentCode = $this->statusCatalog->get((int) $order->getStatusId())?->getEffectiveCode();

        if ($this->quantities->isFullyShipped($order)) {
            if (OrderStatus::CODE_SENT === $currentCode) {
                return;
            }

            $this->moveOrderTo($order, OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_SENT));

            return;
        }

        $partiallyShipped = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PARTIALLY_SHIPPED);

        if (null === $partiallyShipped || $partiallyShipped->getId() === $order->getStatusId() || OrderStatus::CODE_SENT === $currentCode) {
            return;
        }

        $this->moveOrderTo($order, $partiallyShipped);
    }

    /**
     * Whether the shipment is the first of its order that was not cancelled: the one
     * whose tracking number the order carries.
     */
    private function isFirstParcel(OrderShipment $shipment): bool
    {
        $first = OrderShipmentQuery::create()->findFirstParcelOfOrder($shipment->getOrderId());

        return null !== $first && $first->getId() === $shipment->getId();
    }

    private function moveOrderTo(Order $order, ?OrderStatus $status): void
    {
        if (null === $status) {
            return;
        }

        $event = new OrderEvent($order);
        $event->setStatus((int) $status->getId());

        $this->dispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    /**
     * @param array<int, float> $wanted quantity by order line id
     *
     * @throws ShipmentNotAllowedException
     */
    private function assertWithinRemaining(Order $order, array $wanted, ConnectionInterface $connection): void
    {
        $remaining = $this->quantities->remainingByOrderProduct($order, $connection);

        foreach ($wanted as $orderProductId => $quantity) {
            if (!\array_key_exists($orderProductId, $remaining)) {
                throw new ShipmentNotAllowedException(\sprintf('Line %d is not a line of order %s that ships.', $orderProductId, $order->getRef()));
            }

            if ($quantity > $remaining[$orderProductId] + ShippedQuantityCalculator::TOLERANCE) {
                throw new ShipmentNotAllowedException(\sprintf('Only %s left to ship on line %d of order %s, %s asked.', self::formatQuantity($remaining[$orderProductId]), $orderProductId, $order->getRef(), self::formatQuantity($quantity)));
            }
        }
    }

    /**
     * The same line asked twice is one line asked for the sum: the item table holds
     * one row per line and shipment.
     *
     * @param list<ShipmentLine> $lines
     *
     * @return array<int, float>
     *
     * @throws ShipmentNotAllowedException
     */
    private static function mergeLines(array $lines): array
    {
        $wanted = [];

        foreach ($lines as $line) {
            if ($line->quantity <= 0.0) {
                throw new ShipmentNotAllowedException(\sprintf('A shipment line carries a positive quantity, %s given for line %d.', self::formatQuantity($line->quantity), $line->orderProductId));
            }

            $wanted[$line->orderProductId] = ($wanted[$line->orderProductId] ?? 0.0) + $line->quantity;
        }

        if ([] === $wanted) {
            throw new ShipmentNotAllowedException('A shipment carries at least one line.');
        }

        return $wanted;
    }

    /**
     * The order row is the lock every shipment gesture takes: a gesture holding it
     * is the only one reading and writing what is left to ship.
     *
     * @return int|null the status of the order as the lock reads it
     */
    private function lockOrder(Order $order, ConnectionInterface $connection): ?int
    {
        return OrderQuery::create()->lockStatusIdForUpdate((int) $order->getId(), $connection);
    }

    /**
     * Nothing of a cancelled or refunded order leaves: a parcel shipped on it would
     * move it back to a paid-equivalent status.
     *
     * @throws ShipmentNotAllowedException
     */
    private function assertOrderShips(Order $order, ?int $statusId): void
    {
        $code = null === $statusId ? null : $this->statusCatalog->get($statusId)?->getEffectiveCode();

        if (\in_array($code, [OrderStatus::CODE_CANCELED, OrderStatus::CODE_REFUNDED], true)) {
            throw new ShipmentNotAllowedException(\sprintf('Order %s is %s: nothing of it ships.', $order->getRef(), $code));
        }
    }

    /**
     * The order reference followed by the rank of the shipment: `ORD123-2` is the second
     * parcel of order ORD123. Cancelled drafts keep their rank, so a reference is never
     * reused. Called under the order lock, which is what keeps two parcels opened
     * together from sharing a rank.
     */
    private function nextRef(Order $order, ConnectionInterface $connection): string
    {
        $rank = OrderShipmentQuery::create()->filterByOrderId($order->getId())->count($connection) + 1;

        return \sprintf('%s-%d', $order->getRef(), $rank);
    }

    /**
     * The carrier name as the merchant sees it, in the shop's language, kept on the
     * shipment for the day the module is gone.
     */
    private function carrierTitle(?int $moduleId, Order $order): ?string
    {
        if (null === $moduleId) {
            return $order->getDeliveryModuleTitle();
        }

        if ($moduleId === $order->getDeliveryModuleId() && '' !== trim((string) $order->getDeliveryModuleTitle())) {
            return $order->getDeliveryModuleTitle();
        }

        $module = ModuleQuery::create()->findPk($moduleId);

        if (null === $module) {
            throw new ShipmentNotAllowedException(\sprintf('Delivery module %d does not exist.', $moduleId));
        }

        $locale = LangQuery::create()->findOneByByDefault(1)?->getLocale() ?? 'en_US';
        $title = ModuleI18nQuery::create()->filterById($module->getId())->filterByLocale($locale)->findOne()?->getTitle();

        return '' !== trim((string) $title) ? $title : $module->getCode();
    }

    private static function cleanRef(?string $deliveryRef): ?string
    {
        $deliveryRef = trim((string) $deliveryRef);

        return '' === $deliveryRef ? null : $deliveryRef;
    }

    private static function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.');
    }
}
