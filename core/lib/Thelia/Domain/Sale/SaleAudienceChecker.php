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
use Thelia\Model\Customer;
use Thelia\Model\Map\SaleTableMap;
use Thelia\Model\Sale;
use Thelia\Model\SaleCustomerQuery;
use Thelia\Model\SaleQuery;

/**
 * Answers who a sale operation is open to.
 *
 * Every answer is memoised for the request: the catalog asks the same questions
 * once per product otherwise, and the cache bypass of the front asks
 * hasActiveReservedSale() on every single page.
 *
 * The customer is always a parameter, never read from the session here: the
 * scheduled command that opens and closes operations runs with no session at
 * all, and the API is called in-process by the front-office theme, where the
 * token storage is empty. Callers with no customer at hand ask
 * {@see CurrentCustomerProvider} for the one in the session.
 */
class SaleAudienceChecker implements ResetInterface
{
    private ?bool $hasActiveReservedSale = null;

    /** @var array<int, list<int>> the sale IDs each customer is named on, whatever their state */
    private array $targetedSaleIdsByCustomer = [];

    /** @var array<int, list<int>> the running reserved sale IDs each customer is entitled to */
    private array $entitledReservedSaleIdsByCustomer = [];

    /**
     * Whether the customer gets the price of this operation.
     *
     * An operation open to everyone entitles everyone, a visitor with no account
     * included. A reserved one entitles only the customers it names, so an
     * operation reserved for customer groups (US #122, whose groups nothing reads
     * yet) entitles nobody — which is the safe way round.
     */
    public function isCustomerEntitled(Sale $sale, ?Customer $customer): bool
    {
        if (!$sale->isReserved()) {
            return true;
        }

        if (null === $customer) {
            return false;
        }

        return \in_array($sale->getId(), $this->getTargetedSaleIds($customer), true);
    }

    /**
     * Whether any reserved operation is running in the shop right now.
     *
     * One indexed query on (`active`, `audience_mode`), once per request: this is
     * the gate the front-office cache reads to decide whether a page can be
     * shared between visitors at all, so it has to be cheap on a shop that has no
     * reserved operation — which is most shops, most of the time.
     */
    public function hasActiveReservedSale(): bool
    {
        return $this->hasActiveReservedSale ??= SaleQuery::create()
            ->filterByActive(true)
            ->filterByAudienceMode(Sale::AUDIENCE_MODE_PUBLIC, Criteria::NOT_EQUAL)
            ->exists();
    }

    /**
     * The reserved operations the customer is entitled to and that are running now:
     * active, started, and not over.
     *
     * The dates are evaluated in SQL rather than trusted to the `active` flag alone:
     * the flag is only as fresh as the last run of the scheduled command, and an
     * operation whose end date passed ten minutes ago must not price a cart.
     *
     * @return list<int>
     */
    public function getEntitledReservedSaleIds(?Customer $customer): array
    {
        if (null === $customer) {
            return [];
        }

        $customerId = (int) $customer->getId();

        if (isset($this->entitledReservedSaleIdsByCustomer[$customerId])) {
            return $this->entitledReservedSaleIdsByCustomer[$customerId];
        }

        $query = SaleQuery::create()
            ->filterByActive(true)
            ->filterByAudienceMode(Sale::AUDIENCE_MODE_PUBLIC, Criteria::NOT_EQUAL)
            ->useSaleCustomerQuery()
                ->filterByCustomerId($customerId)
            ->endUse();

        $this->filterByRunningDates($query);

        /** @var list<int> $saleIds */
        $saleIds = array_map('intval', $query->select(SaleTableMap::COL_ID)->find()->getData());

        return $this->entitledReservedSaleIdsByCustomer[$customerId] = $saleIds;
    }

    /**
     * Narrows the query to the operations whose date window contains the current
     * instant. A missing bound is an open one: an operation with no end date runs
     * for as long as its active flag says so.
     */
    public function filterByRunningDates(SaleQuery $query, ?\DateTimeInterface $now = null): SaleQuery
    {
        $now ??= new \DateTime();

        return $query
            ->condition('startIsOpen', SaleTableMap::COL_START_DATE.' IS NULL')
            ->condition('startHasPassed', SaleTableMap::COL_START_DATE.' <= ?', $now)
            ->combine(['startIsOpen', 'startHasPassed'], Criteria::LOGICAL_OR)
            ->condition('endIsOpen', SaleTableMap::COL_END_DATE.' IS NULL')
            ->condition('endIsAhead', SaleTableMap::COL_END_DATE.' >= ?', $now)
            ->combine(['endIsOpen', 'endIsAhead'], Criteria::LOGICAL_OR);
    }

    public function reset(): void
    {
        $this->hasActiveReservedSale = null;
        $this->targetedSaleIdsByCustomer = [];
        $this->entitledReservedSaleIdsByCustomer = [];
    }

    /**
     * Every operation the customer is named on, in one query, so that a catalog
     * page asking about fifty operations does not ask the database fifty times.
     *
     * @return list<int>
     */
    private function getTargetedSaleIds(Customer $customer): array
    {
        $customerId = (int) $customer->getId();

        if (isset($this->targetedSaleIdsByCustomer[$customerId])) {
            return $this->targetedSaleIdsByCustomer[$customerId];
        }

        /** @var list<int> $saleIds */
        $saleIds = array_map(
            'intval',
            SaleCustomerQuery::create()
                ->filterByCustomerId($customerId)
                ->select('SaleId')
                ->find()
                ->getData(),
        );

        return $this->targetedSaleIdsByCustomer[$customerId] = $saleIds;
    }
}
