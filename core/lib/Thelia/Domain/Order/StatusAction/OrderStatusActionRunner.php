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

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderStatusActionTrigger;
use Thelia\Domain\Order\Service\OrderStatusCatalog;
use Thelia\Log\Tlog;
use Thelia\Model\Order;
use Thelia\Model\OrderStatusAction;
use Thelia\Model\OrderStatusActionFailure;
use Thelia\Model\OrderStatusActionQuery;

/**
 * Runs the actions configured for a status change, once the core has persisted it.
 *
 * Priority 5 places the runner after the status write (128), the invoice
 * numbering (64) and the coupon accounting (10): what the core does by itself
 * is done when the configured actions start. Actions run in their position
 * order, and an action that fails is recorded and skipped: the status stays
 * changed and the next actions still run.
 */
final class OrderStatusActionRunner
{
    /** @var array<string, list<OrderStatusAction>> */
    private array $actionsByTransition = [];

    public function __construct(
        private readonly OrderStatusActionRegistry $registry,
        private readonly OrderStatusCatalog $catalog,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 5)]
    public function onOrderStatusUpdate(OrderEvent $event): void
    {
        $previousStatusId = $event->getPreviousStatusId();
        $newStatusId = $event->getStatus();

        if (null === $previousStatusId || null === $newStatusId || $previousStatusId === $newStatusId) {
            return;
        }

        $newStatus = $this->catalog->get($newStatusId);

        if (null === $newStatus) {
            return;
        }

        $context = static fn (array $payload, Order $order, ?\Thelia\Model\OrderStatus $previousStatus, \Thelia\Model\OrderStatus $newStatus): OrderStatusActionContext => new OrderStatusActionContext($order, $previousStatus, $newStatus, $payload);
        $order = $event->getOrder();
        $previousStatus = $this->catalog->get($previousStatusId);

        foreach ($this->actionsFor($previousStatusId, $newStatusId) as $action) {
            $this->run($action, $order, static fn (array $payload): OrderStatusActionContext => $context($payload, $order, $previousStatus, $newStatus));
        }
    }

    /**
     * The active actions of a transition, in position order: those fired by
     * entering the new status, and those fired by this precise transition.
     * Equivalences count, so an action on the canonical "paid" status fires
     * for a custom status declared equivalent to it.
     *
     * @return list<OrderStatusAction>
     */
    public function actionsFor(int $previousStatusId, int $newStatusId): array
    {
        $key = $previousStatusId.'>'.$newStatusId;

        if (isset($this->actionsByTransition[$key])) {
            return $this->actionsByTransition[$key];
        }

        $fromIds = $this->catalog->equivalentIds($previousStatusId);
        $toIds = $this->catalog->equivalentIds($newStatusId);

        $actions = OrderStatusActionQuery::create()
            ->filterByActive(true)
            ->filterByToStatusId($toIds, \Propel\Runtime\ActiveQuery\Criteria::IN)
            ->condition('enter', 'order_status_action.trigger_type = ?', OrderStatusActionTrigger::ENTER->value)
            ->condition('transition', 'order_status_action.trigger_type = ?', OrderStatusActionTrigger::TRANSITION->value)
            ->condition('from', 'order_status_action.from_status_id IN ?', $fromIds)
            ->combine(['transition', 'from'], \Propel\Runtime\ActiveQuery\Criteria::LOGICAL_AND, 'precise')
            ->where(['enter', 'precise'], \Propel\Runtime\ActiveQuery\Criteria::LOGICAL_OR)
            ->orderByPosition()
            ->orderById()
            ->find()
            ->getData();

        return $this->actionsByTransition[$key] = array_values($actions);
    }

    public function reset(): void
    {
        $this->actionsByTransition = [];
    }

    /**
     * @param callable(array<string, mixed>): OrderStatusActionContext $contextFor
     */
    private function run(OrderStatusAction $action, Order $order, callable $contextFor): void
    {
        $service = $this->registry->get($action->getActionType());

        if (null === $service) {
            $this->recordFailure($action, $order, \sprintf('Unknown action type "%s": the module providing it is missing or disabled.', $action->getActionType()));

            return;
        }

        try {
            $service->execute($contextFor($service->normalizePayload($action->getDecodedPayload())));
        } catch (\Throwable $throwable) {
            $this->recordFailure($action, $order, $throwable->getMessage());
        }
    }

    private function recordFailure(OrderStatusAction $action, Order $order, string $message): void
    {
        Tlog::getInstance()->addError(\sprintf(
            'Order status action #%d (%s) failed on order %s: %s',
            $action->getId(),
            $action->getActionType(),
            $order->getRef(),
            $message,
        ));

        try {
            (new OrderStatusActionFailure())
                ->setActionId($action->getId())
                ->setOrderId($order->getId())
                ->setMessage($message)
                ->save();
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->addError('Could not record the order status action failure: '.$throwable->getMessage());
        }
    }
}
