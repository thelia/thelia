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

namespace Thelia\Domain\Quote\Acceptance;

use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Exception\OrderStatusRefusedByQuoteException;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\QuoteTransitions;
use Thelia\Model\CartQuery;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * The order of a quote that is cancelled gives the quote back to its buyer: it stands accepted
 * again, and the payment can be tried again. An order that comes back to life takes it again.
 *
 * An order that let go of its quote (the next order was begun) names it no more, but its cart
 * still does. Brought back to life while another order holds the quote, it is refused, before the
 * status is written (the core action is at 128): a quote gives one order at most. Brought back to
 * life while the quote has no live order, it takes the quote again, from the one that was
 * cancelled and still held it, if any.
 *
 * The quote is moved by a conditional write after the status was written, so that whatever else
 * changed it meanwhile is never overwritten.
 */
final readonly class QuoteOrderCancellationListener implements EventSubscriberInterface
{
    public function __construct(
        private QuoteTransitions $transitions,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::ORDER_UPDATE_STATUS => [
                ['takeTheQuoteBack', 160],
                ['onStatusChange', 64],
            ],
        ];
    }

    /**
     * @throws OrderStatusRefusedByQuoteException
     */
    public function takeTheQuoteBack(OrderEvent $event): void
    {
        $order = $event->getOrder();
        $toStatusId = $event->getStatus();

        if (null !== $order->getQuoteId() || null === $toStatusId || null === $order->getId() || !$this->isCancelled((int) $order->getStatusId()) || $this->isCancelled((int) $toStatusId)) {
            return;
        }

        $quoteId = (int) CartQuery::create()->findPk($order->getCartId())?->getQuoteId();

        if (0 === $quoteId) {
            return;
        }

        if ($this->bringBack($order, $quoteId)) {
            // What the status change that follows reads: the order names the quote again, and nothing else of it is written.
            $order->setQuoteId($quoteId)->resetModified(OrderTableMap::COL_QUOTE_ID);

            return;
        }

        $codes = [];

        foreach (OrderStatusQuery::create()->filterById([(int) $order->getStatusId(), (int) $toStatusId])->find() as $status) {
            $codes[(int) $status->getId()] = (string) $status->getCode();
        }

        throw new OrderStatusRefusedByQuoteException((string) $order->getRef(), $codes[(int) $order->getStatusId()] ?? (string) $order->getStatusId(), $codes[(int) $toStatusId] ?? (string) $toStatusId);
    }

    /**
     * Gives the quote back to the order, unless another order that is alive holds it. The quote is
     * locked first, so that two orders that come back for the same quote go one after the other;
     * the orders that hold it are then read with a locking read, which sees what is committed now
     * even when this runs inside a transaction whose view was fixed earlier. An order that was
     * cancelled and still held the quote lets it go, and only such an order: the write names the
     * status it lets go on, and the number of rows it changes is checked.
     *
     * @return bool false when another order that is alive holds the quote, or when the quote could
     *              not be taken
     */
    private function bringBack(Order $order, int $quoteId): bool
    {
        $connection = Propel::getWriteConnection(OrderTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $lock = $connection->prepare('SELECT `id` FROM `quote` WHERE `id` = :id FOR UPDATE');
            $lock->bindValue(':id', $quoteId, \PDO::PARAM_INT);
            $lock->execute();

            if (false === $lock->fetch(\PDO::FETCH_ASSOC)) {
                $connection->commit();

                return true;
            }

            $holders = $connection->prepare('SELECT `id`, `status_id` FROM `order` WHERE `quote_id` = :quote AND `id` <> :order FOR UPDATE');
            $holders->bindValue(':quote', $quoteId, \PDO::PARAM_INT);
            $holders->bindValue(':order', (int) $order->getId(), \PDO::PARAM_INT);
            $holders->execute();
            $cancelledHolders = 0;

            while (false !== ($holder = $holders->fetch(\PDO::FETCH_ASSOC))) {
                if (!$this->isCancelled((int) $holder['status_id'])) {
                    $connection->commit();
                    OrderTableMap::clearInstancePool();

                    return false;
                }

                ++$cancelledHolders;
            }

            $letGo = $connection->prepare(
                'UPDATE `order` INNER JOIN `order_status` ON `order_status`.`id` = `order`.`status_id` '
                .'SET `order`.`quote_id` = NULL '
                .'WHERE `order`.`quote_id` = :quote AND `order`.`id` <> :order AND `order_status`.`code` = :cancelled',
            );
            $letGo->bindValue(':quote', $quoteId, \PDO::PARAM_INT);
            $letGo->bindValue(':order', (int) $order->getId(), \PDO::PARAM_INT);
            $letGo->bindValue(':cancelled', OrderStatus::CODE_CANCELED);
            $letGo->execute();

            $take = $connection->prepare('UPDATE `order` SET `quote_id` = :quote WHERE `id` = :order AND `quote_id` IS NULL');
            $take->bindValue(':quote', $quoteId, \PDO::PARAM_INT);
            $take->bindValue(':order', (int) $order->getId(), \PDO::PARAM_INT);
            $take->execute();

            if ($letGo->rowCount() !== $cancelledHolders || 1 !== $take->rowCount()) {
                $connection->rollBack();
                OrderTableMap::clearInstancePool();

                return false;
            }

            $connection->commit();
        } catch (\Throwable $failure) {
            $connection->rollBack();

            throw $failure;
        }

        OrderTableMap::clearInstancePool();

        return true;
    }

    public function onStatusChange(OrderEvent $event): void
    {
        $order = $event->getOrder();
        $quoteId = $order->getQuoteId();
        $previousStatusId = $event->getPreviousStatusId();

        if (null === $quoteId || null === $previousStatusId) {
            return;
        }

        $wasCancelled = $this->isCancelled($previousStatusId);
        $isCancelled = $this->isCancelled((int) $order->getStatusId());

        if ($wasCancelled === $isCancelled) {
            return;
        }

        [$from, $to] = $isCancelled
            ? [QuoteState::Ordered, QuoteState::Accepted]
            : [QuoteState::Accepted, QuoteState::Ordered];

        $this->transitions->assertCanMove($from, $to);

        $statement = Propel::getWriteConnection(QuoteTableMap::DATABASE_NAME)->prepare(
            'UPDATE `quote` SET `state` = :to, `updated_at` = NOW() WHERE `id` = :id AND `state` = :from'
        );
        $statement->bindValue(':to', $to->value);
        $statement->bindValue(':from', $from->value);
        $statement->bindValue(':id', (int) $quoteId, \PDO::PARAM_INT);
        $statement->execute();

        QuoteTableMap::clearInstancePool();
    }

    private function isCancelled(int $statusId): bool
    {
        return OrderStatusQuery::create()->findPk($statusId)?->isCancelled() ?? false;
    }
}
