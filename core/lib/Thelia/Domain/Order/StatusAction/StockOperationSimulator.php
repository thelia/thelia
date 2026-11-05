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

namespace Thelia\Domain\Order\StatusAction;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\GetStockUpdateOperationOnOrderStatusChangeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;

/**
 * Tells which stock operation moving an order to a status would apply, and what chose it,
 * without moving anything.
 *
 * It asks the same question as Thelia\Action\Order on a real status change, through the same
 * event and listeners (the fixed rule of the core, the stock actions, any module), flagged as
 * a simulation so that no listener writes: what it announces is what the change would do.
 */
final readonly class StockOperationSimulator
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function simulate(Order $order, OrderStatus $newStatus): StockOperationDecision
    {
        if ($newStatus->getId() === $order->getStatusId()) {
            return new StockOperationDecision(GetStockUpdateOperationOnOrderStatusChangeEvent::DO_NOTHING, null);
        }

        $event = new GetStockUpdateOperationOnOrderStatusChangeEvent($order, $newStatus, simulation: true);
        $this->dispatcher->dispatch($event, TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE);

        return new StockOperationDecision($event->getOperation(), $event->getDecidingActionId());
    }
}
