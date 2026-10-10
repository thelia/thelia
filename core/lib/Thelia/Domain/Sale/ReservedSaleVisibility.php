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

namespace Thelia\Domain\Sale;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Model\Customer;
use Thelia\Model\Map\SaleTableMap;
use Thelia\Model\Sale;
use Thelia\Model\SaleQuery;

/**
 * Who gets to see a reserved operation, and the products it holds.
 *
 * Two rules, one place, so that the API extensions and the legacy loops can
 * never disagree on them:
 *
 *  - an operation reserved for named customers is only part of the shop for the
 *    customers it names;
 *  - a shopkeeper can go further and make it a private drop — `hide_products` —
 *    and then its products do not exist at all for anybody else.
 *
 * Both are enforced in the query rather than on the way out, so that a
 * collection, an item read and a count all agree on what the catalog holds: a
 * product left reachable by its id is not hidden, it is only harder to find.
 *
 * A shop with no running reserved operation and no module rule pays one indexed
 * existence check per request and nothing more: no criterion is added to any
 * query, and the statements are the ones it ran before the feature existed.
 *
 * Which products the visitor sees is no longer decided here: ProductVisibility
 * joins the private drops (ReservedSaleProductRule) to the rules of the modules.
 * applyTo(), visibleProductClause(), hiddenSaleIds() and entitledHidingSaleIds()
 * are kept and hand over to them, so that a caller reading the catalog through
 * this class still gets every rule.
 */
class ReservedSaleVisibility implements ResetInterface
{
    private readonly ReservedSaleProductRule $reservedSaleProductRule;

    private readonly ProductVisibility $productVisibility;

    public function __construct(
        private readonly SaleAudienceChecker $saleAudienceChecker,
        private readonly CurrentCustomerProvider $currentCustomerProvider,
        ?ProductVisibility $productVisibility = null,
        ?ReservedSaleProductRule $reservedSaleProductRule = null,
    ) {
        // Both or neither: the rule given to one and not the other would keep two
        // memories of the private drops, and reset() would empty only one of them.
        if ((null === $productVisibility) !== (null === $reservedSaleProductRule)) {
            throw new \InvalidArgumentException('Give ReservedSaleVisibility both its ProductVisibility and its ReservedSaleProductRule, or neither.');
        }

        // Built by hand when they are not given, so that a caller constructing this
        // class with its first two arguments keeps the reserved operations.
        $this->reservedSaleProductRule = $reservedSaleProductRule ?? new ReservedSaleProductRule($saleAudienceChecker, $currentCustomerProvider);
        $this->productVisibility = $productVisibility ?? new ProductVisibility($this->reservedSaleProductRule, $currentCustomerProvider);
    }

    /**
     * Whether any reserved operation is running in the shop at all. It says nothing
     * about the rules of the modules: never read it to skip applyTo() or
     * visibleProductClause(), which apply those rules whether an operation runs or not.
     */
    public function hasActiveReservedSale(): bool
    {
        return $this->saleAudienceChecker->hasActiveReservedSale();
    }

    /**
     * Narrows a catalog query to the products the current visitor may see, through
     * ProductVisibility: the private drops and the rules of the modules.
     */
    public function applyTo(ModelCriteria $query, string $productIdColumn): void
    {
        $this->productVisibility->applyTo($query, $productIdColumn);
    }

    /**
     * @return string|null null when nothing is hidden from the visitor
     */
    public function visibleProductClause(string $productIdColumn): ?string
    {
        return $this->productVisibility->visibleProductClause($productIdColumn);
    }

    /**
     * Narrows a query over the operations themselves to the ones the current visitor
     * is part of: every public one, and the reserved ones they are named on.
     */
    public function applyToSales(SaleQuery $query): void
    {
        if (!$this->hasActiveReservedSale()) {
            return;
        }

        $entitledSaleIds = $this->entitledSaleIds();

        if ([] === $entitledSaleIds) {
            $query->filterByAudienceMode(Sale::AUDIENCE_MODE_PUBLIC);

            return;
        }

        $query
            ->condition('openToEverybody', SaleTableMap::COL_AUDIENCE_MODE.' = ?', Sale::AUDIENCE_MODE_PUBLIC)
            ->condition('openToThisCustomer', SaleTableMap::COL_ID.' IN ('.implode(', ', $entitledSaleIds).')')
            ->combine(['openToEverybody', 'openToThisCustomer'], Criteria::LOGICAL_OR);
    }

    /**
     * The same rule as {@see applyToSales()}, as a fragment for a join condition:
     * a query joining the operations of a product to read something off them has to
     * skip the ones the visitor is not part of, and a join has no criteria of its own.
     *
     * @param string $saleAlias the alias the `sale` table is joined under
     */
    public function saleIsOpenToVisitorClause(string $saleAlias): string
    {
        $entitledSaleIds = $this->entitledSaleIds();
        $isPublic = \sprintf('`%s`.`audience_mode` = %d', $saleAlias, Sale::AUDIENCE_MODE_PUBLIC);

        if ([] === $entitledSaleIds) {
            return $isPublic;
        }

        return \sprintf(
            '(%s OR `%s`.`id` IN (%s))',
            $isPublic,
            $saleAlias,
            implode(', ', $entitledSaleIds),
        );
    }

    /**
     * The running reserved operations the current visitor is named on.
     *
     * @return list<int>
     */
    public function entitledSaleIds(): array
    {
        return $this->saleAudienceChecker->getEntitledReservedSaleIds($this->currentCustomer());
    }

    /**
     * The running reserved operations that hide their products from the current
     * visitor: the ones they are not named on.
     *
     * @return list<int>
     */
    public function hiddenSaleIds(): array
    {
        return $this->reservedSaleProductRule->hiddenSaleIds();
    }

    /**
     * The running operations that hide their products and that the current visitor
     * is named on — the ones that hand them a product back.
     *
     * @return list<int>
     */
    public function entitledHidingSaleIds(): array
    {
        return $this->reservedSaleProductRule->entitledHidingSaleIds();
    }

    public function currentCustomer(): ?Customer
    {
        return $this->currentCustomerProvider->getCurrentCustomer();
    }

    public function reset(): void
    {
        $this->reservedSaleProductRule->reset();
    }
}
