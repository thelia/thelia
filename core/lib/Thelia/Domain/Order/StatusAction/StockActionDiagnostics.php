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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Order\Enum\OrderStatusActionTrigger;
use Thelia\Domain\Order\Exception\InvalidOrderStatusActionPayloadException;
use Thelia\Domain\Order\Service\OrderStatusCatalog;
use Thelia\Domain\Order\StatusAction\Effect\AdjustStockAction;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderStatusAction;
use Thelia\Model\OrderStatusActionQuery;

/**
 * What the back office warns about on the stock actions: an action another one placed
 * before it always overrides, and the modules of a limit that are gone or switched off.
 *
 * Compares the actions as a status change reads them (OrderStatusActionRunner::actionsFor()):
 * an action of a canonical status also applies to the custom statuses equivalent to it, so
 * it can override an action of one of them.
 */
final readonly class StockActionDiagnostics
{
    public function __construct(
        private OrderStatusActionRegistry $registry,
        private OrderStatusCatalog $catalog,
    ) {
    }

    /**
     * The active stock actions that never apply, each with the action that wins over it.
     *
     * @return array<int, int> id of the action without effect => id of the action placed before it
     */
    public function overriddenActions(): array
    {
        $stockAction = $this->registry->get(AdjustStockAction::getType());

        if (null === $stockAction) {
            return [];
        }

        $actions = OrderStatusActionQuery::create()
            ->filterByActionType(AdjustStockAction::getType())
            ->filterByActive(true)
            ->orderByPosition()
            ->orderById()
            ->find()
            ->getData();

        $activeModuleCodes = $this->activeModuleCodes();
        $payloads = [];

        foreach ($actions as $action) {
            try {
                $payload = $stockAction->normalizePayload($action->getDecodedPayload());
            } catch (InvalidOrderStatusActionPayloadException) {
                // A refused payload never applies: it overrides nothing.
                continue;
            }

            // As the resolver applies them: a limit that names a module gone or switched off
            // makes its action ignored, so it overrides nothing (missingModules() says why).
            foreach ([AdjustStockAction::FIELD_PAYMENT_MODULES, AdjustStockAction::FIELD_DELIVERY_MODULES] as $field) {
                if ([] !== array_diff($payload[$field] ?? [], $activeModuleCodes)) {
                    continue 2;
                }
            }

            $payloads[$action->getId()] = $payload;
        }

        $overridden = [];

        foreach ($actions as $laterIndex => $later) {
            if (!isset($payloads[$later->getId()])) {
                continue;
            }

            foreach (\array_slice($actions, 0, $laterIndex) as $earlier) {
                if (isset($payloads[$earlier->getId()]) && $this->covers($earlier, $payloads[$earlier->getId()], $later, $payloads[$later->getId()])) {
                    $overridden[$later->getId()] = $earlier->getId();

                    break;
                }
            }
        }

        return $overridden;
    }

    /**
     * The module codes of a stock action limit that are not installed or not active.
     *
     * @return list<string>
     */
    public function missingModules(OrderStatusAction $action): array
    {
        $stockAction = $this->registry->get(AdjustStockAction::getType());

        if (null === $stockAction || AdjustStockAction::getType() !== $action->getActionType()) {
            return [];
        }

        try {
            $payload = $stockAction->normalizePayload($action->getDecodedPayload());
        } catch (InvalidOrderStatusActionPayloadException) {
            return [];
        }

        $codes = array_merge(
            $payload[AdjustStockAction::FIELD_PAYMENT_MODULES] ?? [],
            $payload[AdjustStockAction::FIELD_DELIVERY_MODULES] ?? [],
        );

        if ([] === $codes) {
            return [];
        }

        return array_values(array_diff($codes, $this->activeModuleCodes($codes)));
    }

    /**
     * @param list<string>|null $amongCodes
     *
     * @return list<string>
     */
    private function activeModuleCodes(?array $amongCodes = null): array
    {
        $query = ModuleQuery::create()->filterByActivate(1);

        if (null !== $amongCodes) {
            $query->filterByCode($amongCodes, Criteria::IN);
        }

        return array_values(array_map(strval(...), $query->select(['Code'])->find()->getData()));
    }

    /**
     * @param array<string, mixed> $earlierPayload
     * @param array<string, mixed> $laterPayload
     */
    private function covers(OrderStatusAction $earlier, array $earlierPayload, OrderStatusAction $later, array $laterPayload): bool
    {
        // The later action is read for a change into its status, which also reads the actions of
        // the canonical status it is equivalent to; same for the status a transition comes from.
        if (!\in_array($earlier->getToStatusId(), $this->catalog->equivalentIds((int) $later->getToStatusId()), true)) {
            return false;
        }

        $earlierIsEnter = OrderStatusActionTrigger::ENTER->value === $earlier->getTriggerType();
        $sameTransition = OrderStatusActionTrigger::TRANSITION->value === $later->getTriggerType()
            && null !== $later->getFromStatusId()
            && \in_array($earlier->getFromStatusId(), $this->catalog->equivalentIds((int) $later->getFromStatusId()), true);

        if (!$earlierIsEnter && !$sameTransition) {
            return false;
        }

        foreach ([AdjustStockAction::FIELD_PAYMENT_MODULES, AdjustStockAction::FIELD_DELIVERY_MODULES] as $field) {
            $earlierModules = $earlierPayload[$field] ?? [];
            $laterModules = $laterPayload[$field] ?? [];

            // No limit covers every module; a limit covers a later one only if it lists all its modules.
            if ([] !== $earlierModules && ([] === $laterModules || [] !== array_diff($laterModules, $earlierModules))) {
                return false;
            }
        }

        return true;
    }
}
