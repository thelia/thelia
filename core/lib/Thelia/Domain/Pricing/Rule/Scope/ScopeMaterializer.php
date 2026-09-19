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

namespace Thelia\Domain\Pricing\Rule\Scope;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Model\Map\CatalogPriceRuleTableMap;

/**
 * Writes down which sale elements a rule covers, from its criteria.
 *
 * The scope of a rule follows the catalog: a product filed in the Winter category
 * on January 15th is in the January rule from that moment. Rather than re-run the
 * criteria on every read, their answer is kept in
 * `catalog_price_rule_product_sale_elements` and refreshed when the rule or the
 * catalog changes - one statement per rule, whatever the size of the catalog. The
 * table is what the back office counts, what the price writer prices, and what
 * the resolver joins for a named customer.
 *
 * The criterion types combine with AND, the targets of one type with OR, and a type
 * absent from the rule restricts nothing. A type no resolver answers for - a module
 * that was turned off - makes the rule cover nothing: a rule must never widen
 * because part of its definition became unreadable.
 */
class ScopeMaterializer
{
    public const TABLE = 'catalog_price_rule_product_sale_elements';

    /** @var array<string, ScopeCriterionResolverInterface> */
    private array $resolvers = [];

    /**
     * @param iterable<ScopeCriterionResolverInterface> $criterionResolvers
     */
    public function __construct(
        #[AutowireIterator(ScopeCriterionResolverInterface::TAG)]
        iterable $criterionResolvers,
    ) {
        foreach ($criterionResolvers as $resolver) {
            $this->resolvers[$resolver->type()] = $resolver;
        }
    }

    /**
     * @return list<string> the criterion types the shop can resolve
     */
    public function knownCriterionTypes(): array
    {
        return array_keys($this->resolvers);
    }

    /**
     * Rebuilds the whole scope of one rule.
     */
    public function materializeRule(CatalogPriceRule $rule, ?ConnectionInterface $con = null): ScopeChange
    {
        $con ??= Propel::getWriteConnection(CatalogPriceRuleTableMap::DATABASE_NAME);

        $before = $this->coveredIds($rule->getId(), null, $con);
        $this->deleteScope($rule->getId(), null, $con);
        $unknown = $this->insertScope($rule, null, $con);
        $after = $this->coveredIds($rule->getId(), null, $con);

        return new ScopeChange($rule->getId(), $before, $after, $unknown);
    }

    /**
     * Re-evaluates one product against every rule, after the product changed.
     *
     * @return list<ScopeChange> one per rule, restricted to the product's sale elements
     */
    public function materializeProduct(int $productId, ?ConnectionInterface $con = null): array
    {
        $con ??= Propel::getWriteConnection(CatalogPriceRuleTableMap::DATABASE_NAME);
        $changes = [];

        foreach (CatalogPriceRuleQuery::create()->orderById()->find($con) as $rule) {
            $before = $this->coveredIds($rule->getId(), $productId, $con);
            $this->deleteScope($rule->getId(), $productId, $con);
            $unknown = $this->insertScope($rule, $productId, $con);
            $after = $this->coveredIds($rule->getId(), $productId, $con);

            $changes[] = new ScopeChange($rule->getId(), $before, $after, $unknown);
        }

        return $changes;
    }

    /**
     * Rebuilds the scope of every rule, the catch-up the recompute command runs.
     *
     * @return list<ScopeChange>
     */
    public function materializeAll(?ConnectionInterface $con = null): array
    {
        $con ??= Propel::getWriteConnection(CatalogPriceRuleTableMap::DATABASE_NAME);
        $changes = [];

        foreach (CatalogPriceRuleQuery::create()->orderById()->find($con) as $rule) {
            $changes[] = $this->materializeRule($rule, $con);
        }

        return $changes;
    }

    /**
     * The predicate a rule's criteria compile to, over the `pse` and `p` aliases, or
     * null when a criterion type cannot be resolved.
     *
     * Shared with the preview, which runs the same selection without writing it.
     *
     * @param array<string, list<int>> $criteriaByType
     *
     * @return array{0: string, 1: list<string>} the predicate, and the unknown types
     */
    public function predicateFor(CatalogPriceRule $rule, array $criteriaByType): array
    {
        $predicates = [];
        $unknown = [];

        foreach ($criteriaByType as $type => $targetIds) {
            if ([] === $targetIds) {
                continue;
            }

            $resolver = $this->resolvers[$type] ?? null;

            if (null === $resolver) {
                $unknown[] = $type;

                continue;
            }

            $predicates[] = $resolver->predicate(array_values(array_map('intval', $targetIds)), $rule);
        }

        if ([] !== $unknown) {
            return ['0 = 1', $unknown];
        }

        return [[] === $predicates ? '1 = 1' : implode(' AND ', $predicates), []];
    }

    /**
     * @return list<string> the unknown criterion types
     */
    private function insertScope(CatalogPriceRule $rule, ?int $productId, ConnectionInterface $con): array
    {
        [$predicate, $unknown] = $this->predicateFor($rule, $rule->getCriteriaByType());

        $sql = \sprintf(
            'INSERT INTO `%s` (catalog_price_rule_id, product_sale_elements_id)
             SELECT %d, pse.id
             FROM `product_sale_elements` pse
             INNER JOIN `product` p ON p.id = pse.product_id
             WHERE %s%s',
            self::TABLE,
            $rule->getId(),
            $predicate,
            null === $productId ? '' : \sprintf(' AND p.id = %d', $productId),
        );

        $con->exec($sql);

        return $unknown;
    }

    private function deleteScope(int $ruleId, ?int $productId, ConnectionInterface $con): void
    {
        $con->exec(\sprintf(
            'DELETE FROM `%s` WHERE catalog_price_rule_id = %d%s',
            self::TABLE,
            $ruleId,
            null === $productId ? '' : \sprintf(
                ' AND product_sale_elements_id IN (SELECT id FROM `product_sale_elements` WHERE product_id = %d)',
                $productId,
            ),
        ));
    }

    /**
     * @return list<int>
     */
    private function coveredIds(int $ruleId, ?int $productId, ConnectionInterface $con): array
    {
        $statement = $con->query(\sprintf(
            'SELECT scope.product_sale_elements_id
             FROM `%s` scope%s
             WHERE scope.catalog_price_rule_id = %d%s',
            self::TABLE,
            null === $productId ? '' : ' INNER JOIN `product_sale_elements` pse ON pse.id = scope.product_sale_elements_id',
            $ruleId,
            null === $productId ? '' : \sprintf(' AND pse.product_id = %d', $productId),
        ));

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN) ?: []);
    }
}
