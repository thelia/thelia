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

namespace Thelia\Domain\Order\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Core\Event\Order\GetStockUpdateOperationOnOrderStatusChangeEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\StatusAction\OrderStatusActionRunner;
use Thelia\Domain\Order\StatusAction\RefusedStockPayload;
use Thelia\Domain\Order\StatusAction\StockOperationActionResolver;
use Thelia\Domain\Order\StatusAction\StockOperationDecision;
use Thelia\Model\Order;

/**
 * Lets the stock actions of the back office decide the stock operation of a status change,
 * in place of the fixed rule of the core, and keeps the final decision for the order history.
 *
 * The decision is taken when Thelia\Action\Order asks for it, inside the transaction of the
 * status change: the core moves the stock there, so a stock it cannot take refuses the
 * status change, and a status change that fails leaves the stock untouched.
 *
 * What is remembered is keyed by the order object, which the decision event and the
 * status event share: a change that fails leaves its entry with that object, and the
 * next decision for it replaces the entry.
 */
final class StockOperationActionListener implements ResetInterface
{
    /** @var \WeakMap<Order, StockOperationDecision> the last decision applied */
    private \WeakMap $decisionByOrder;

    /** @var \WeakMap<Order, list<RefusedStockPayload>> the payloads refused by the last decision, not recorded yet */
    private \WeakMap $refusedPayloadsByOrder;

    public function __construct(
        private readonly StockOperationActionResolver $resolver,
        private readonly OrderStatusActionRunner $runner,
    ) {
        $this->reset();
    }

    /**
     * After the fixed rule of the core (128), which it overrides when a stock action matches.
     */
    #[AsEventListener(event: TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE, priority: 64)]
    public function applyStockAction(GetStockUpdateOperationOnOrderStatusChangeEvent $event): void
    {
        $order = $event->getOrder();
        $resolution = $this->resolver->resolve($order, $event->getNewOrderStatus());

        // A simulation writes nothing, a refused payload included.
        if (!$event->isSimulation()) {
            unset($this->refusedPayloadsByOrder[$order]);

            if ([] !== $resolution->refusedPayloads) {
                $this->refusedPayloadsByOrder[$order] = $resolution->refusedPayloads;
            }
        }

        if (null === $resolution->decision) {
            return;
        }

        $event
            ->setOperation($resolution->decision->operation)
            ->setDecidingActionId($resolution->decision->actionId);
    }

    /**
     * Last of all, so what is kept is what the core is about to apply, a module included.
     */
    #[AsEventListener(event: TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE, priority: -1024)]
    public function rememberDecision(GetStockUpdateOperationOnOrderStatusChangeEvent $event): void
    {
        if ($event->isSimulation()) {
            return;
        }

        $this->decisionByOrder[$event->getOrder()] = new StockOperationDecision($event->getOperation(), $event->getDecidingActionId());
    }

    /**
     * Records the payloads the stock actions refused, once the status change is committed
     * (the core listener, 128, commits inside its own call): written during the decision they
     * would sit in the transaction of the change, rolled back with it, and an insert that fails
     * there would make the change fail. The price is that a refused status change records
     * nothing. Just below the history line (127), so that line is not delayed.
     */
    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 126)]
    public function recordRefusedPayloads(OrderEvent $event): void
    {
        $order = $event->getOrder();
        $refusedPayloads = $this->refusedPayloadsByOrder[$order] ?? [];
        unset($this->refusedPayloadsByOrder[$order]);

        foreach ($refusedPayloads as $refusedPayload) {
            $this->runner->recordFailure($refusedPayload->action, $order, $refusedPayload->message);
        }
    }

    /**
     * The decision of the status change the order just went through, forgotten once read.
     */
    public function takeDecisionFor(Order $order): ?StockOperationDecision
    {
        $decision = $this->decisionByOrder[$order] ?? null;
        unset($this->decisionByOrder[$order]);

        return $decision;
    }

    public function reset(): void
    {
        $this->decisionByOrder = new \WeakMap();
        $this->refusedPayloadsByOrder = new \WeakMap();
    }
}
