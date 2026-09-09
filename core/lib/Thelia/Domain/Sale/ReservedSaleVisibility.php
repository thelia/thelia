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
use Thelia\Model\Customer;
use Thelia\Model\Map\SaleProductTableMap;
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
 * A shop with no running reserved operation pays one indexed existence check per
 * request and nothing more — no criterion is added to any query, and the
 * statements are the ones it ran before the feature existed.
 */
class ReservedSaleVisibility implements ResetInterface
{
    /** @var array<int, array{hidden: list<int>, entitled: list<int>}> the hiding operations each customer is out of, and in */
    private array $hidingSaleIdsByCustomer = [];

    public function __construct(
        private readonly SaleAudienceChecker $saleAudienceChecker,
        private readonly CurrentCustomerProvider $currentCustomerProvider,
    ) {
    }

    /**
     * Whether any reserved operation is running in the shop at all — the gate every
     * caller reads before doing anything more expensive.
     */
    public function hasActiveReservedSale(): bool
    {
        return $this->saleAudienceChecker->hasActiveReservedSale();
    }

    /**
     * Narrows a catalog query to the products the current visitor is allowed to see.
     *
     * A product is out of the catalog when a hidden operation covers it and none of
     * the hidden operations covering it is open to the visitor. Being left out of one
     * private drop is not what hides a product from somebody — being left out of every
     * one of them is, and a customer named on one of two overlapping operations reads
     * their catalog through the one they are part of.
     *
     * @param string $productIdColumn the qualified column holding the product id in
     *                                the query being narrowed — `product.id` for the
     *                                catalog itself, `product_sale_elements.product_id`
     *                                for its sale elements
     */
    public function applyTo(ModelCriteria $query, string $productIdColumn): void
    {
        $hiddenSaleIds = $this->hiddenSaleIds();

        if ([] === $hiddenSaleIds) {
            return;
        }

        $clause = \sprintf(
            'NOT %s',
            $this->coveredByAnyOfClause($productIdColumn, $hiddenSaleIds),
        );

        $entitledHidingSaleIds = $this->entitledHidingSaleIds();

        if ([] !== $entitledHidingSaleIds) {
            $clause = \sprintf(
                '(%s OR %s)',
                $clause,
                $this->coveredByAnyOfClause($productIdColumn, $entitledHidingSaleIds),
            );
        }

        $query->where($clause);
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
        return $this->hidingSaleIds()['hidden'];
    }

    /**
     * The running operations that hide their products and that the current visitor
     * is named on — the ones that hand them a product back.
     *
     * @return list<int>
     */
    public function entitledHidingSaleIds(): array
    {
        return $this->hidingSaleIds()['entitled'];
    }

    public function currentCustomer(): ?Customer
    {
        return $this->currentCustomerProvider->getCurrentCustomer();
    }

    public function reset(): void
    {
        $this->hidingSaleIdsByCustomer = [];
    }

    /**
     * Every running operation hiding its products, split into the ones the current
     * visitor is out of and the ones they are part of.
     *
     * Answered from an indexed existence check first — most shops run no reserved
     * operation at all, and those must not pay for a list nobody will read. The split
     * is made here rather than by the database, so that both halves cost the one
     * statement the single half used to.
     *
     * @return array{hidden: list<int>, entitled: list<int>}
     */
    private function hidingSaleIds(): array
    {
        if (!$this->hasActiveReservedSale()) {
            return ['hidden' => [], 'entitled' => []];
        }

        $customer = $this->currentCustomer();
        $customerId = (int) ($customer?->getId() ?? 0);

        if (isset($this->hidingSaleIdsByCustomer[$customerId])) {
            return $this->hidingSaleIdsByCustomer[$customerId];
        }

        $query = SaleQuery::create()
            ->filterByActive(true)
            ->filterByHideProducts(true)
            ->filterByAudienceMode(Sale::AUDIENCE_MODE_PUBLIC, Criteria::NOT_EQUAL);

        // The dates decide, not the active flag alone: the flag is only as fresh as
        // the last run of the scheduled command, and an operation that ended ten
        // minutes ago must not keep a product out of the catalog.
        $this->saleAudienceChecker->filterByRunningDates($query);

        /** @var list<int> $hidingSaleIds */
        $hidingSaleIds = array_map('intval', $query->select(SaleTableMap::COL_ID)->find()->getData());

        $entitledSaleIds = $this->saleAudienceChecker->getEntitledReservedSaleIds($customer);

        return $this->hidingSaleIdsByCustomer[$customerId] = [
            'hidden' => array_values(array_diff($hidingSaleIds, $entitledSaleIds)),
            'entitled' => array_values(array_intersect($hidingSaleIds, $entitledSaleIds)),
        ];
    }

    /**
     * Whether the product the query is reading is part of any of these operations.
     *
     * The ids are read back from the database as integers, so they go into the
     * statement as they are: there is no value here to bind, and a raw clause is what
     * lets the same criterion be expressed against either column.
     *
     * @param list<int> $saleIds
     */
    private function coveredByAnyOfClause(string $productIdColumn, array $saleIds): string
    {
        return \sprintf(
            'EXISTS (SELECT 1 FROM `%s` WHERE `%s`.`product_id` = %s AND `%s`.`sale_id` IN (%s))',
            SaleProductTableMap::TABLE_NAME,
            SaleProductTableMap::TABLE_NAME,
            $productIdColumn,
            SaleProductTableMap::TABLE_NAME,
            implode(', ', $saleIds),
        );
    }
}
