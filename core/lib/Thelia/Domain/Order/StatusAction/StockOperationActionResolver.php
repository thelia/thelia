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
use Thelia\Domain\Order\Exception\InvalidOrderStatusActionPayloadException;
use Thelia\Domain\Order\Service\OrderStatusCatalog;
use Thelia\Domain\Order\StatusAction\Effect\AdjustStockAction;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;

/**
 * Finds the stock action that decides the stock operation of a status change: the first
 * active "adjust stock" action of the change, in the order of the back office, whose
 * payment and delivery modules match the order. None found, the fixed rule of the core
 * keeps deciding.
 *
 * Reads only: a payload it refuses is handed back with the decision, for the caller to
 * record once the status change is committed.
 */
final readonly class StockOperationActionResolver
{
    public function __construct(
        private OrderStatusActionRunner $runner,
        private OrderStatusActionRegistry $registry,
        private OrderStatusCatalog $catalog,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function resolve(Order $order, OrderStatus $newStatus): StockActionResolution
    {
        $previousStatusId = $order->getStatusId();
        $newStatusId = $newStatus->getId();

        if (null === $previousStatusId || null === $newStatusId || $previousStatusId === $newStatusId) {
            return StockActionResolution::none();
        }

        // Same guard as the runner: moving to a status equivalent to the current one
        // enters nothing, so no stock action of the canonical status applies again.
        $previousStatus = $this->catalog->get($previousStatusId);
        $effectiveNewStatus = $this->catalog->get($newStatusId) ?? $newStatus;

        if (null !== $previousStatus && $previousStatus->getEffectiveCode() === $effectiveNewStatus->getEffectiveCode()) {
            return StockActionResolution::none();
        }

        $stockAction = $this->registry->get(AdjustStockAction::getType());

        if (null === $stockAction) {
            return StockActionResolution::none();
        }

        $refusedPayloads = [];
        // Read once, and only when an action carries a limit.
        $activeModuleCodes = null;

        foreach ($this->runner->actionsFor($previousStatusId, $newStatusId) as $action) {
            if (AdjustStockAction::getType() !== $action->getActionType()) {
                continue;
            }

            try {
                $payload = $stockAction->normalizePayload($action->getDecodedPayload());
            } catch (InvalidOrderStatusActionPayloadException $exception) {
                $refusedPayloads[] = new RefusedStockPayload($action, $exception->getMessage());

                continue;
            }

            if (!$this->matchesModules($payload, $order, $activeModuleCodes)) {
                continue;
            }

            return new StockActionResolution(
                new StockOperationDecision(
                    $this->operationFor($payload[AdjustStockAction::FIELD_OPERATION], $order),
                    $action->getId(),
                ),
                $refusedPayloads,
            );
        }

        return new StockActionResolution(null, $refusedPayloads);
    }

    /**
     * @param array<string, mixed>    $payload
     * @param array<int, string>|null $activeModuleCodes
     */
    private function matchesModules(array $payload, Order $order, ?array &$activeModuleCodes): bool
    {
        return $this->matchesModule($payload[AdjustStockAction::FIELD_PAYMENT_MODULES] ?? [], $order->getPaymentModuleId(), $activeModuleCodes)
            && $this->matchesModule($payload[AdjustStockAction::FIELD_DELIVERY_MODULES] ?? [], $order->getDeliveryModuleId(), $activeModuleCodes);
    }

    /**
     * A limit that names a module no longer installed or active is ignored with its action,
     * whatever its other modules: the merchant set it for modules that are not all there,
     * and the back office tells them so. It never widens to every order either.
     *
     * @param list<string>            $moduleCodes
     * @param array<int, string>|null $activeModuleCodes the code of each active module by id, loaded on first use
     */
    private function matchesModule(array $moduleCodes, ?int $orderModuleId, ?array &$activeModuleCodes): bool
    {
        if ([] === $moduleCodes) {
            return true;
        }

        if (null === $orderModuleId) {
            return false;
        }

        if (null === $activeModuleCodes) {
            $activeModuleCodes = [];

            foreach (ModuleQuery::create()->filterByActivate(1)->find() as $module) {
                $activeModuleCodes[$module->getId()] = $module->getCode();
            }
        }

        if ([] !== array_diff($moduleCodes, $activeModuleCodes)) {
            return false;
        }

        return isset($activeModuleCodes[$orderModuleId]) && \in_array($activeModuleCodes[$orderModuleId], $moduleCodes, true);
    }

    /**
     * The fixed rule weighs the stock the payment module took, or did not take, when the
     * order was placed, as long as the order is unpaid and never changed status. A stock
     * action keeps that protection: it does not take out a second time the stock already
     * taken at creation, nor put back a stock that was never taken.
     */
    private function operationFor(string $operation, Order $order): int
    {
        if (AdjustStockAction::OPERATION_NONE === $operation) {
            return GetStockUpdateOperationOnOrderStatusChangeEvent::DO_NOTHING;
        }

        $increase = AdjustStockAction::OPERATION_INCREASE === $operation;

        if ($order->isNotPaid(false) && !$order->hasChangedStatusSinceCreation()) {
            $takenAtCreation = $order->isStockManagedOnOrderCreation($this->dispatcher);

            if ($increase !== $takenAtCreation) {
                return GetStockUpdateOperationOnOrderStatusChangeEvent::DO_NOTHING;
            }
        }

        return $increase
            ? GetStockUpdateOperationOnOrderStatusChangeEvent::INCREASE_STOCK
            : GetStockUpdateOperationOnOrderStatusChangeEvent::DECREASE_STOCK;
    }
}
