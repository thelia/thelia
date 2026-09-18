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

namespace Thelia\Action;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleCreateEvent;
use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleDeleteEvent;
use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleRecomputeEvent;
use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleToggleActivityEvent;
use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Pricing\Rule\CatalogPriceRuleDefinitionValidator;
use Thelia\Domain\Pricing\Rule\Exception\CatalogPriceRuleNotFoundException;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Domain\Pricing\Rule\Storage\ScopeReader;
use Thelia\Model\CatalogPriceRule as CatalogPriceRuleModel;
use Thelia\Model\CatalogPriceRuleCriterion;
use Thelia\Model\CatalogPriceRuleCriterionQuery;
use Thelia\Model\CatalogPriceRuleCustomer;
use Thelia\Model\CatalogPriceRuleCustomerQuery;
use Thelia\Model\CatalogPriceRuleEffectCurrency;
use Thelia\Model\CatalogPriceRuleEffectCurrencyQuery;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Model\Map\CatalogPriceRuleTableMap;

/**
 * Writes catalog price rules, and keeps what they price in step.
 *
 * A rule is one row plus three lists - criteria, per-currency values, named
 * customers - written together in one transaction, then handed to the repricer,
 * which rematerializes the scope and rewrites the stored prices of everything
 * the change touched. Nothing here writes into the catalog itself.
 */
class CatalogPriceRule extends BaseAction implements EventSubscriberInterface
{
    public function __construct(
        private readonly CatalogPriceRuleDefinitionValidator $validator,
        private readonly RuleRepricer $repricer,
        private readonly ScopeReader $scopeReader,
    ) {
    }

    public function create(CatalogPriceRuleCreateEvent $event): void
    {
        $this->validator->validate($event);

        $rule = $this->transactional(function (ConnectionInterface $con) use ($event): CatalogPriceRuleModel {
            $rule = new CatalogPriceRuleModel();
            $this->writeDefinition($rule, $event, $con);

            return $rule;
        });

        $event->setCatalogPriceRule($rule);
        $this->repricer->afterRuleChanged($rule);
    }

    public function update(CatalogPriceRuleUpdateEvent $event): void
    {
        $this->validator->validate($event);
        $rule = $this->ruleOrFail($event->getCatalogPriceRuleId());

        $this->transactional(function (ConnectionInterface $con) use ($rule, $event): void {
            $this->writeDefinition($rule, $event, $con);
        });

        $event->setCatalogPriceRule($rule);
        $this->repricer->afterRuleChanged($rule);
    }

    public function delete(CatalogPriceRuleDeleteEvent $event): void
    {
        $rule = $this->ruleOrFail($event->getCatalogPriceRuleId());
        $formerScope = $this->scopeReader->productSaleElementsOf($rule->getId());

        // The scope rows and the stored segments carrying the rule go with it, by
        // cascade; what other rules still say about those sale elements is rewritten.
        $rule->delete();

        $event->setCatalogPriceRule($rule);
        $this->repricer->afterRuleDeleted($formerScope);
    }

    public function toggleActivity(CatalogPriceRuleToggleActivityEvent $event): void
    {
        $rule = $this->ruleOrFail($event->getCatalogPriceRuleId());
        $wanted = $event->getWantedActiveState() ?? !$rule->getActive();

        if ($wanted !== (bool) $rule->getActive()) {
            $rule->setActive($wanted)->save();
        }

        $event->setCatalogPriceRule($rule);
        $this->repricer->afterRuleChanged($rule);
    }

    public function recompute(CatalogPriceRuleRecomputeEvent $event): void
    {
        $ruleId = $event->getCatalogPriceRuleId();

        if (null === $ruleId) {
            $this->repricer->repriceDirtyRules();

            return;
        }

        $rule = $this->ruleOrFail($ruleId);
        $event->setCatalogPriceRule($rule);
        $this->repricer->repriceRule($rule);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CATALOG_PRICE_RULE_CREATE => ['create', 128],
            TheliaEvents::CATALOG_PRICE_RULE_UPDATE => ['update', 128],
            TheliaEvents::CATALOG_PRICE_RULE_DELETE => ['delete', 128],
            TheliaEvents::CATALOG_PRICE_RULE_TOGGLE_ACTIVITY => ['toggleActivity', 128],
            TheliaEvents::CATALOG_PRICE_RULE_RECOMPUTE => ['recompute', 128],
        ];
    }

    private function writeDefinition(CatalogPriceRuleModel $rule, CatalogPriceRuleCreateEvent $event, ConnectionInterface $con): void
    {
        $rule
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setDescription($event->getDescription())
            ->setActive($event->isActive())
            ->setPriority($event->getPriority())
            ->setStopProcessing($event->isStopProcessing())
            ->setStartDate(null === $event->getStartDate() ? null : \DateTime::createFromInterface($event->getStartDate()))
            ->setEndDate(null === $event->getEndDate() ? null : \DateTime::createFromInterface($event->getEndDate()))
            ->setEffectType($event->getEffectType())
            ->setPercentageValue(null === $event->getPercentageValue() ? null : (string) $event->getPercentageValue())
            ->setAudienceMode($event->getAudienceMode())
            ->setDisplayInitialPrice($event->isDisplayInitialPrice())
            ->setIncludeSubcategories($event->isIncludeSubcategories())
            ->save($con);

        $this->syncCriteria($rule, $event->getCriteria(), $con);
        $this->syncEffectValues($rule, $event->getEffectValuesByCurrency(), $con);
        $this->syncCustomers($rule, $event->getAudienceMode(), $event->getCustomerIds(), $con);
    }

    /**
     * Delete then insert, like the sale selection: a criterion taken out of the
     * definition loses its row, not just the reading of it.
     *
     * @param array<string, list<int>> $criteria
     */
    private function syncCriteria(CatalogPriceRuleModel $rule, array $criteria, ConnectionInterface $con): void
    {
        CatalogPriceRuleCriterionQuery::create()->filterByCatalogPriceRuleId($rule->getId())->delete($con);

        foreach ($criteria as $type => $targetIds) {
            foreach ($targetIds as $targetId) {
                (new CatalogPriceRuleCriterion())
                    ->setCatalogPriceRuleId($rule->getId())
                    ->setType($type)
                    ->setTargetId($targetId)
                    ->save($con);
            }
        }
    }

    /**
     * @param array<int, float> $valuesByCurrency
     */
    private function syncEffectValues(CatalogPriceRuleModel $rule, array $valuesByCurrency, ConnectionInterface $con): void
    {
        CatalogPriceRuleEffectCurrencyQuery::create()->filterByCatalogPriceRuleId($rule->getId())->delete($con);

        if (CatalogPriceRuleModel::EFFECT_TYPE_PERCENTAGE === (int) $rule->getEffectType()) {
            return;
        }

        foreach ($valuesByCurrency as $currencyId => $value) {
            (new CatalogPriceRuleEffectCurrency())
                ->setCatalogPriceRuleId($rule->getId())
                ->setCurrencyId($currencyId)
                ->setValue((string) $value)
                ->save($con);
        }
    }

    /**
     * A rule open to everyone keeps no audience at all: leaving the rows behind
     * would silently reserve it again the moment someone flips the mode back.
     *
     * @param list<int> $customerIds
     */
    private function syncCustomers(CatalogPriceRuleModel $rule, int $audienceMode, array $customerIds, ConnectionInterface $con): void
    {
        CatalogPriceRuleCustomerQuery::create()->filterByCatalogPriceRuleId($rule->getId())->delete($con);

        if (CatalogPriceRuleModel::AUDIENCE_MODE_CUSTOMERS !== $audienceMode) {
            return;
        }

        foreach ($customerIds as $customerId) {
            (new CatalogPriceRuleCustomer())
                ->setCatalogPriceRuleId($rule->getId())
                ->setCustomerId($customerId)
                ->save($con);
        }
    }

    private function ruleOrFail(int $ruleId): CatalogPriceRuleModel
    {
        return CatalogPriceRuleQuery::create()->findPk($ruleId) ?? throw CatalogPriceRuleNotFoundException::withId($ruleId);
    }

    /**
     * @template T
     *
     * @param callable(ConnectionInterface): T $work
     *
     * @return T
     */
    private function transactional(callable $work): mixed
    {
        $con = Propel::getWriteConnection(CatalogPriceRuleTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $result = $work($con);
            $con->commit();

            return $result;
        } catch (\Throwable $throwable) {
            $con->rollBack();

            throw $throwable;
        }
    }
}
