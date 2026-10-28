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

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\ProductFilter\ProductPriceSortRequests;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\ProductFilter\ProductPublicPriceOrder;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;
use Thelia\Api\Bridge\Propel\State\Pagination\PresortedPropelModelPager;
use Thelia\Api\EventListener\ProductPriceCurrencyListener;
use Thelia\Domain\Catalog\Product\VisitorPriceReader;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Sorts a product collection on what the visitor pays, when the price order was asked and the
 * visitor may be owed a price other than the public one (ProductPriceOrderFilter leaves a
 * note on the query then).
 *
 * It answers the collection itself, after every filter and every visibility rule has
 * narrowed the query (a result extension runs once all the others have applied to it, and
 * comes before the pagination one): the whole narrowed set is priced by the resolver of the
 * cards ({@see VisitorPriceReader}), sorted, sliced to the page, and only the members of the
 * page are loaded, in that order. The page and the total are those of the whole narrowed set,
 * as the database would have given them.
 *
 * Pricing every product of a set is a cost that grows with it: past the ceiling (5 000 products
 * by default, THELIA_API_PRODUCT_PRICE_SORT_MAX_PRODUCTS) the extension steps aside, the query
 * is ordered in SQL on the public price (a listing of that size ranks on the catalog) and the
 * pagination extension serves it as for any other order.
 */
final readonly class ProductPriceSortExtension implements QueryResultCollectionExtensionInterface
{
    public function __construct(
        private ProductPriceSortRequests $sortRequests,
        private ProductPublicPriceOrder $publicPriceOrder,
        private VisitorPriceReader $visitorPriceReader,
        private ProductPriceCurrencyListener $currencyResolver,
        private ?Pagination $pagination = null,
        #[Autowire('%thelia.api.product_price_sort.max_products%')]
        private int $maxProducts = 5000,
    ) {
    }

    /**
     * Ahead of the pagination extension, which would otherwise count and slice the query
     * for nothing: the first result extension that answers wins.
     */
    public static function getDefaultPriority(): int
    {
        return 100;
    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
    }

    /**
     * Whatever the resource: a resource of a module that carries the price order filter on a
     * product query is sorted like Product is. A query nobody asked a price order of is left
     * to the next extension, getResult() answers null.
     */
    public function supportsResult(string $resourceClass, ?Operation $operation = null, array $context = []): bool
    {
        return true;
    }

    public function getResult(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = [])
    {
        $direction = $this->sortRequests->directionOn($query);

        if (null === $direction) {
            return null;
        }

        $this->sortRequests->forget($query);
        $rankedIds = $this->rankedIds($query, $direction);

        if (null === $rankedIds) {
            // Too many products to price one by one: the public prices order the query, the
            // pagination extension takes it from here.
            $this->publicPriceOrder->apply($query, $direction);

            return null;
        }

        $paginated = null !== $this->pagination && $this->pagination->isEnabled($operation, $context);
        [$page, $offset, $limit] = $paginated ? $this->pagination->getPagination($operation, $context) : [1, 0, 0];
        $total = \count($rankedIds);

        if ($paginated && $limit > 0) {
            // A page past the last one is the last one, as the pager of the database says it.
            $page = max(1, min($page, (int) ceil($total / $limit)));
            $offset = ($page - 1) * $limit;
        }

        $pageQuery = $this->pageQuery($query, $limit > 0 ? \array_slice($rankedIds, $offset, $limit) : $rankedIds);

        return $paginated
            ? new PresortedPropelModelPager($pageQuery, $limit > 0 ? $limit : max(1, $total), $page, $total)
            : $pageQuery->find();
    }

    /**
     * The ids of the narrowed set, the cheapest first or the dearest first; null when the set
     * is past the ceiling.
     *
     * The query already carries the other orders asked: they are kept, with the id last, and
     * since the sort below is stable they settle the products priced alike.
     *
     * @return list<int>|null
     */
    private function rankedIds(ModelCriteria $query, string $direction): ?array
    {
        // Only the id: the columns the serialization eager loads stay out of a query that is
        // there to rank. Clearing them clears the virtual columns too, which an order of the
        // query may be written on (withColumn() then orderBy() on its alias): they stay.
        $idsQuery = (clone $query)->clearSelectColumns();

        foreach ($query->getAsColumns() as $alias => $clause) {
            $idsQuery->addAsColumn($alias, $clause);
        }

        $idsQuery->addAscendingOrderByColumn(ProductTableMap::COL_ID);

        /** @var list<int|string|array{Id: int|string}> $rows an id each, or the row with its virtual columns beside it */
        $rows = $idsQuery->select('Id')->limit($this->maxProducts + 1)->find()->getData();

        if (\count($rows) > $this->maxProducts) {
            return null;
        }

        $productIds = array_values(array_unique(array_map(static fn (int|string|array $row): int => (int) (\is_array($row) ? $row['Id'] : $row), $rows)));

        if ([] === $productIds) {
            return [];
        }

        $saleElementByProduct = [];

        /** @var array{Id: int, ProductId: int} $row */
        foreach (ProductSaleElementsQuery::create()
            ->filterByIsDefault(true)
            ->filterByProductId($productIds, Criteria::IN)
            ->select(['Id', 'ProductId'])
            ->find()
            ->getData() as $row) {
            $saleElementByProduct[(int) $row['ProductId']] = (int) $row['Id'];
        }

        $prices = $this->visitorPriceReader->resolve(
            array_values($saleElementByProduct),
            $this->currencyResolver->currentCurrency(),
            alsoWithinTheCatalog: true,
        );

        // The price of the card is rounded to the cent: two products showing the same price
        // are priced alike, whatever the digits behind.
        $ranked = [];

        foreach ($productIds as $productId) {
            $price = isset($saleElementByProduct[$productId]) ? ($prices[$saleElementByProduct[$productId]] ?? null) : null;
            $ranked[] = [$productId, null === $price ? null : round($price->chargedPrice(), 2)];
        }

        $sign = OrderFilter::DIRECTION_DESC === $direction ? -1 : 1;

        // Stable: equal prices keep the order of the query. Priceless products come last,
        // in both directions.
        usort($ranked, static fn (array $a, array $b): int => match (true) {
            null === $a[1] && null === $b[1] => 0,
            null === $a[1] => 1,
            null === $b[1] => -1,
            default => $sign * ($a[1] <=> $b[1]),
        });

        return array_column($ranked, 0);
    }

    /**
     * The narrowed query, reduced to the members of the page and ordered as the page is. It
     * keeps the joins the serialization eager loads.
     *
     * @param list<int> $pageIds
     */
    private function pageQuery(ModelCriteria $query, array $pageIds): ModelCriteria
    {
        $pageQuery = clone $query;
        $pageQuery->clearOrderByColumns();

        if ([] === $pageIds) {
            return $pageQuery->where('1 <> 1');
        }

        return $pageQuery
            ->filterBy('Id', $pageIds, Criteria::IN)
            ->addAscendingOrderByColumn(\sprintf('FIELD(%s, %s)', ProductTableMap::COL_ID, implode(', ', $pageIds)));
    }
}
