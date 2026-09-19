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

namespace Thelia\Domain\Pricing\Rule;

use Propel\Runtime\Propel;
use Psr\Log\LoggerInterface;
use Thelia\Domain\Pricing\Rule\Scope\ScopeChange;
use Thelia\Domain\Pricing\Rule\Scope\ScopeMaterializer;
use Thelia\Domain\Pricing\Rule\Storage\PublicPriceSegmentWriter;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleCriterionQuery;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\CatalogPriceRuleTableMap;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Keeps the stored scope and prices in line with the rules and the catalog.
 *
 * Every change to a rule or to a product goes through here: the scope is
 * rematerialized, then everything that left it or entered it is repriced. A
 * batch small enough is repriced inline, in the request that changed things; a
 * larger one is left to the recompute command, with the rule marked dirty so the
 * back office can say the prices are pending. The threshold is a shop setting.
 */
class RuleRepricer
{
    public const INLINE_LIMIT_CONFIG = 'catalog_price_rule_inline_recompute_limit';

    public const DEFAULT_INLINE_LIMIT = 5000;

    public function __construct(
        private readonly ScopeMaterializer $scopeMaterializer,
        private readonly PublicPriceSegmentWriter $segmentWriter,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * After a rule was created, changed or turned on or off.
     */
    public function afterRuleChanged(CatalogPriceRule $rule): ScopeChange
    {
        $change = $this->scopeMaterializer->materializeRule($rule);

        if ([] !== $change->unknownCriterionTypes) {
            $this->logger->warning('A catalog price rule carries criteria no installed module resolves; it covers nothing until they are removed.', [
                'rule_id' => $rule->getId(),
                'types' => $change->unknownCriterionTypes,
            ]);
        }

        $this->reprice($change->touchedProductSaleElementsIds(), $rule);

        return $change;
    }

    /**
     * After a rule was deleted: its own rows are gone with it, the other rules may
     * still cover what it covered.
     *
     * @param list<int> $formerScope
     */
    public function afterRuleDeleted(array $formerScope): void
    {
        $this->reprice($formerScope, null);
    }

    /**
     * After a product changed in a way that may move it in or out of a scope, or
     * change the price a rule starts from.
     *
     * @return list<ScopeChange>
     */
    public function afterProductChanged(int $productId): array
    {
        $changes = $this->scopeMaterializer->materializeProduct($productId);

        /** @var list<int> $productSaleElementsIds */
        $productSaleElementsIds = array_map('intval', ProductSaleElementsQuery::create()
            ->filterByProductId($productId)
            ->select('Id')
            ->find()
            ->getData());

        $this->reprice($productSaleElementsIds, null);

        return $changes;
    }

    /**
     * After the object a criterion names changed or disappeared: a category moved
     * under another parent, a brand deleted. Every rule naming it - and, for a
     * category, every rule naming any category, since the tree below it moved - is
     * rematerialized and repriced.
     *
     * @param int|null $targetId the object, or null for every rule carrying the type
     */
    public function afterCriterionTargetChanged(string $type, ?int $targetId): void
    {
        $query = CatalogPriceRuleCriterionQuery::create()->filterByType($type);

        if (null !== $targetId) {
            $query->filterByTargetId($targetId);
        }

        /** @var list<int|string> $ruleIds */
        $ruleIds = $query->select('CatalogPriceRuleId')->distinct()->find()->getData();

        foreach (CatalogPriceRuleQuery::create()->filterById(array_map('intval', $ruleIds), \Propel\Runtime\ActiveQuery\Criteria::IN)->find() as $rule) {
            $this->afterRuleChanged($rule);
        }
    }

    /**
     * Repricing everything one rule covers, without touching its scope: what a rule
     * needs when the catalog prices it starts from changed, not its criteria.
     */
    public function repriceRule(CatalogPriceRule $rule): void
    {
        $this->reprice($this->scopeMaterializer->materializeRule($rule)->after, $rule);
    }

    /**
     * Marks every turned-on rule as owing a recompute: what a change that moves
     * every price at once - a tax rule, a currency rate - asks for.
     */
    public function markAllDirty(): void
    {
        Propel::getWriteConnection(CatalogPriceRuleTableMap::DATABASE_NAME)
            ->exec('UPDATE catalog_price_rule SET dirty = 1 WHERE active = 1');
    }

    /**
     * Clears the recompute mark of every rule: what a full run owes once it has
     * written the prices of all of them itself.
     */
    public function markAllComputed(): void
    {
        Propel::getWriteConnection(CatalogPriceRuleTableMap::DATABASE_NAME)
            ->exec('UPDATE catalog_price_rule SET dirty = 0, computed_at = NOW()');
    }

    /**
     * The rules still owing a recompute, repriced in full; what the command runs.
     *
     * @return int the number of rules brought up to date
     */
    public function repriceDirtyRules(): int
    {
        $count = 0;

        foreach (CatalogPriceRuleQuery::create()->filterByDirty(true)->find() as $rule) {
            $change = $this->scopeMaterializer->materializeRule($rule);
            $this->segmentWriter->recomputeForProductSaleElements($change->touchedProductSaleElementsIds());
            $this->markComputed($rule);
            ++$count;
        }

        return $count;
    }

    public function inlineLimit(): int
    {
        return max(0, (int) ConfigQuery::read(self::INLINE_LIMIT_CONFIG, self::DEFAULT_INLINE_LIMIT));
    }

    /**
     * @param list<int> $productSaleElementsIds
     */
    private function reprice(array $productSaleElementsIds, ?CatalogPriceRule $rule): void
    {
        if (\count($productSaleElementsIds) > $this->inlineLimit()) {
            if (null !== $rule) {
                $rule->setDirty(true)->save();
            } else {
                $this->markAllDirty();
            }

            $this->logger->info('A catalog price rule change touches more sale elements than the inline limit; the recompute command will finish it.', [
                'rule_id' => $rule?->getId(),
                'count' => \count($productSaleElementsIds),
            ]);

            return;
        }

        $this->segmentWriter->recomputeForProductSaleElements($productSaleElementsIds);

        if (null !== $rule) {
            $this->markComputed($rule);
        }
    }

    private function markComputed(CatalogPriceRule $rule): void
    {
        $rule->setDirty(false)->setComputedAt(new \DateTime())->save();
    }
}
