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
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Model\Map\SaleProductTableMap;
use Thelia\Model\Map\SaleTableMap;
use Thelia\Model\Sale;
use Thelia\Model\SaleQuery;

/**
 * The products a private drop — a reserved operation with `hide_products` — keeps
 * out of the catalog for everybody it does not name.
 *
 * The first rule ProductVisibility applies, before the rules of the modules. It
 * has their shape without their tag: it is part of the core, not a rule a module
 * declares, and a shop does not run a module rule because it may run a reserved
 * operation one day.
 *
 * It reads the visitor itself, and only once an operation runs: a shop with no
 * running reserved operation pays one indexed existence check per request and
 * nothing more, and no criterion is added to any query.
 */
class ReservedSaleProductRule implements ResetInterface
{
    /** @var array<int, array{hidden: list<int>, entitled: list<int>}> the hiding operations each customer is out of, and in */
    private array $hidingSaleIdsByCustomer = [];

    public function __construct(
        private readonly SaleAudienceChecker $saleAudienceChecker,
        private readonly CurrentCustomerProvider $currentCustomerProvider,
    ) {
    }

    /**
     * A product is out of the catalog when a hidden operation covers it and none of
     * the hidden operations covering it is open to the visitor. Being left out of one
     * private drop is not what hides a product from somebody — being left out of every
     * one of them is, and a customer named on one of two overlapping operations reads
     * their catalog through the one they are part of.
     *
     * @return string|null null when no running operation hides anything from the visitor
     */
    public function visibleProductClause(string $productIdColumn): ?string
    {
        $hiddenSaleIds = $this->hiddenSaleIds();

        if ([] === $hiddenSaleIds) {
            return null;
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

        return $clause;
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
        if (!$this->saleAudienceChecker->hasActiveReservedSale()) {
            return ['hidden' => [], 'entitled' => []];
        }

        $customer = $this->currentCustomerProvider->getCurrentCustomer();
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
