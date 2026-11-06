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

namespace Thelia\Domain\Order\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\GetStockUpdateOperationOnOrderStatusChangeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Stock\StockReservationService;
use Thelia\Domain\Order\StatusAction\Effect\AdjustStockAction;
use Thelia\Domain\Order\StatusAction\OrderStatusActionRunner;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatusQuery;

/**
 * The stock rows a change of status takes from, locked before anything is read in the transaction
 * that changes the status: the reservations of the other carts are then read as they stand, and
 * a payment never takes the unit another cart holds.
 *
 * Only when the shop reserves its stock, and only when the change takes the stock: a payment of
 * an unpaid order whose stock was left for it (as ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE
 * answers), or an action `adjust_stock` (decrease) of the transition. An order whose stock was
 * taken when it was placed locks nothing when it is paid.
 *
 * Every path that changes a status takes its locks in the same order: the row of the order, then
 * these rows in ascending id.
 */
final readonly class PaymentStockLock
{
    public function __construct(
        private StockReservationService $stockReservation,
        private ?OrderStatusActionRunner $actionRunner = null,
    ) {
    }

    /**
     * Read before the transaction, with no lock: a plain read inside it before the locks would
     * freeze its view too early. The operation the listeners answer is asked once, here, and
     * handed on with the plan.
     *
     * @throws PropelException
     */
    public function planFor(Order $order, int $newStatus, EventDispatcherInterface $dispatcher): StatusStockPlan
    {
        $previousStatusId = (int) $order->getStatusId();

        if (!$this->stockReservation->isActive() || $newStatus === $previousStatusId) {
            return new StatusStockPlan($previousStatusId, $newStatus, null, []);
        }

        $operation = null;
        $rows = [];
        $newStatusModel = OrderStatusQuery::create()->findPk($newStatus);

        if (null !== $newStatusModel && $order->isNotPaid(false) && $newStatusModel->isPaid(false)) {
            $operation = new GetStockUpdateOperationOnOrderStatusChangeEvent($order, $newStatusModel);
            $dispatcher->dispatch($operation, TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE);

            if ($operation->getOperation() === $operation::DECREASE_STOCK) {
                $rows = self::stockRowsOf($order->getOrderProducts()->getData());
            }
        }

        if ($this->transitionTakesStockByAnAction($previousStatusId, $newStatus)) {
            $rows = array_merge($rows, self::stockRowsOf($order->getOrderProducts()->getData()));
        }

        $rows = array_values(array_unique($rows));
        sort($rows);

        return new StatusStockPlan($previousStatusId, $newStatus, $operation, $rows);
    }

    /**
     * @param list<int> $productSaleElementsIds
     */
    public function lock(array $productSaleElementsIds, ConnectionInterface $connection): void
    {
        $this->stockReservation->lockStockRows($productSaleElementsIds, $connection);
    }

    /**
     * The sale elements whose stock the lines of an order move: none for the line of a pack.
     *
     * @param list<OrderProduct> $orderProducts
     *
     * @return list<int>
     */
    public static function stockRowsOf(array $orderProducts): array
    {
        $ids = [];

        foreach ($orderProducts as $orderProduct) {
            if ($orderProduct->movesStockOfItsOwn() && null !== $orderProduct->getProductSaleElementsId()) {
                $ids[] = (int) $orderProduct->getProductSaleElementsId();
            }
        }

        return array_values(array_unique($ids));
    }

    private function transitionTakesStockByAnAction(int $previousStatusId, int $newStatus): bool
    {
        if (null === $this->actionRunner) {
            return false;
        }

        foreach ($this->actionRunner->actionsFor($previousStatusId, $newStatus) as $action) {
            if (AdjustStockAction::getType() === $action->getActionType()
                && AdjustStockAction::OPERATION_DECREASE === ($action->getDecodedPayload()[AdjustStockAction::FIELD_OPERATION] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
