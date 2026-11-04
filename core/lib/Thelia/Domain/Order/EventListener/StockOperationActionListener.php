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
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\StatusAction\StockOperationActionResolver;
use Thelia\Domain\Order\StatusAction\StockOperationDecision;

/**
 * Lets the stock actions of the back office decide the stock operation of a status change,
 * in place of the fixed rule of the core, and keeps the final decision for the order history.
 *
 * The decision is taken when Thelia\Action\Order asks for it, inside the transaction of the
 * status change: the core moves the stock there, so a stock it cannot take refuses the
 * status change, and a status change that fails leaves the stock untouched.
 */
final class StockOperationActionListener implements ResetInterface
{
    /** @var array<int, StockOperationDecision> the last decision applied, by order id */
    private array $decisionByOrder = [];

    public function __construct(
        private readonly StockOperationActionResolver $resolver,
    ) {
    }

    /**
     * After the fixed rule of the core (128), which it overrides when a stock action matches.
     */
    #[AsEventListener(event: TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE, priority: 64)]
    public function applyStockAction(GetStockUpdateOperationOnOrderStatusChangeEvent $event): void
    {
        $decision = $this->resolver->resolve($event->getOrder(), $event->getNewOrderStatus(), !$event->isSimulation());

        if (null === $decision) {
            return;
        }

        $event
            ->setOperation($decision->operation)
            ->setDecidingActionId($decision->actionId);
    }

    /**
     * Last of all, so what is kept is what the core is about to apply, a module included.
     */
    #[AsEventListener(event: TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE, priority: -1024)]
    public function rememberDecision(GetStockUpdateOperationOnOrderStatusChangeEvent $event): void
    {
        $orderId = $event->getOrder()->getId();

        if ($event->isSimulation() || null === $orderId) {
            return;
        }

        $this->decisionByOrder[$orderId] = new StockOperationDecision($event->getOperation(), $event->getDecidingActionId());
    }

    /**
     * The decision of the status change the order just went through, forgotten once read.
     */
    public function takeDecisionFor(int $orderId): ?StockOperationDecision
    {
        $decision = $this->decisionByOrder[$orderId] ?? null;
        unset($this->decisionByOrder[$orderId]);

        return $decision;
    }

    public function reset(): void
    {
        $this->decisionByOrder = [];
    }
}
