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

namespace Thelia\Domain\Catalog\Product;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Domain\Sale\ReservedSaleProductRule;

/**
 * Which products of the catalog the current visitor may see, in one place, so that
 * the API extensions, the facets, the quick order and the legacy loops can never
 * disagree on it.
 *
 * Two kinds of rules, joined with AND: the private drops of the reserved
 * operations, then every rule a module declares through
 * ProductVisibilityRuleInterface. A product is visible when each of them lets it
 * through.
 *
 * Enforced in the query rather than on the way out, so that a collection, an item
 * read and a count all agree on what the catalog holds: a product left reachable
 * by its id is not hidden, it is only harder to find. Only front readers ask: the
 * back office and the admin API are never narrowed.
 *
 * A shop with no running reserved operation and no module rule pays one indexed
 * existence check per request and nothing more: no criterion is added to any query.
 */
final readonly class ProductVisibility
{
    /**
     * @param iterable<ProductVisibilityRuleInterface> $moduleRules
     */
    public function __construct(
        private ReservedSaleProductRule $reservedSaleRule,
        private CurrentCustomerProvider $currentCustomerProvider,
        #[AutowireIterator(ProductVisibilityRuleInterface::TAG)]
        private iterable $moduleRules = [],
    ) {
    }

    /**
     * Narrows a catalog query to the products the current visitor may see.
     *
     * @param string $productIdColumn the qualified column holding the product id in
     *                                the query being narrowed — `product.id` for the
     *                                catalog itself, `product_sale_elements.product_id`
     *                                for its sale elements
     */
    public function applyTo(ModelCriteria $query, string $productIdColumn): void
    {
        $clause = $this->visibleProductClause($productIdColumn);

        if (null !== $clause) {
            $query->where($clause);
        }
    }

    /**
     * The rule of {@see applyTo()} as a fragment, for a query that reaches the
     * product through a subquery of its own: the combination links of a video
     * carry no product column, the video they point at does.
     *
     * @return string|null null when nothing is hidden from the visitor
     */
    public function visibleProductClause(string $productIdColumn): ?string
    {
        $clauses = [];
        $reservedSaleClause = $this->reservedSaleRule->visibleProductClause($productIdColumn);

        if (null !== $reservedSaleClause) {
            $clauses[] = $reservedSaleClause;
        }

        // Read once, and only when a module rule is there to be given it: the rules
        // see the visitor the reserved operations see.
        $customer = null;
        $customerIsRead = false;

        foreach ($this->moduleRules as $rule) {
            if (!$customerIsRead) {
                $customer = $this->currentCustomerProvider->getCurrentCustomer();
                $customerIsRead = true;
            }

            $ruleClause = $rule->visibleProductClause($productIdColumn, $customer);

            if (null === $ruleClause) {
                continue;
            }

            // An empty clause would turn every front read of the catalog into an SQL
            // error naming nobody: the rule at fault is named here instead, and the
            // catalog is never opened in its place.
            if ('' === trim($ruleClause)) {
                throw new \LogicException(\sprintf('%s returned an empty clause: it returns null when it hides nothing.', $rule::class));
            }

            $clauses[] = \sprintf('(%s)', $ruleClause);
        }

        // A single clause is returned as it is, so that a shop running reserved
        // operations alone reads the statement it read before; several are enclosed
        // together, so that a caller writing the fragment into a wider expression
        // cannot split them.
        return match (\count($clauses)) {
            0 => null,
            1 => $clauses[0],
            default => '('.implode(' AND ', $clauses).')',
        };
    }

    /**
     * Whether a module declared a rule of its own: the catalog may then differ from
     * one visitor to the next even when no reserved operation runs.
     */
    public function hasModuleRules(): bool
    {
        // The iterator of the container counts its services without building them.
        if ($this->moduleRules instanceof \Countable) {
            return \count($this->moduleRules) > 0;
        }

        foreach ($this->moduleRules as $rule) {
            return true;
        }

        return false;
    }
}
