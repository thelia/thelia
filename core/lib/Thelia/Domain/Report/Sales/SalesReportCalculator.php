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

namespace Thelia\Domain\Report\Sales;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\StatementInterface;
use Propel\Runtime\Propel;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Order\Sql\OrderAmountSql;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatusQuery;

/**
 * The sales of a period by category, by product and by customer, and how they add up to the
 * revenue: the one computation the back-office screen and the exports both read.
 *
 * Every line is totalled with its taxes by the rounding rule of its order (OrderAmountSql),
 * so that the lines of an order add up to the goods Order::getTotalAmount() states, and the
 * amounts are summed whatever the currency of the order, as the dashboard sums them.
 *
 * A line is filed in the default category of the product its reference names today; a line
 * whose product was deleted, or is filed nowhere, is uncategorized, and a service line (a
 * gift wrapping) is a service whatever it names. The components of a bundle are priced at
 * zero, the line of the bundle holding the price: they are not counted as sold, neither in
 * the quantities nor in the orders.
 */
final readonly class SalesReportCalculator
{
    private const string SQL_DATE_FORMAT = 'Y-m-d H:i:s';

    private const int MAX_PER_PAGE = 500;

    /** Far beyond any shop, low enough for the offset of its last row to stay an integer. */
    private const int MAX_PAGE = 1_000_000;

    /**
     * How far up the tree a category is followed: far beyond any real catalogue, it only
     * keeps a parent loop in the data from walking forever.
     */
    private const int MAX_CATEGORY_DEPTH = 64;

    /**
     * A group of lines is a sale when one of its lines is counted, or when it carries an
     * amount: the components of a bundle alone are not.
     */
    private const string SOLD_HAVING = 'COUNT(sold_line.counted_order_id) > 0 OR SUM(sold_line.line_total) <> 0';

    public function __construct(private ?ConnectionInterface $connection = null)
    {
    }

    /**
     * Each category sums its own lines and the lines of its subcategories. The cumulation is
     * made in SQL, on the ancestors of the categories sold (WITH RECURSIVE): an order touching
     * two subcategories is one order of their parent, and only the order ids can tell, so the
     * distinct count cannot be added up from the children in PHP without fetching every order.
     *
     * @return list<CategorySalesRow> tree order: a parent, then its children depth first, siblings by revenue descending;
     *                                the uncategorized goods then the services last, when they sold; categories without sales are left out
     */
    public function byCategory(SalesReportCriteria $criteria, string $locale): array
    {
        $current = $this->categoryFigures($criteria);
        $linesTotal = $current['total']['revenue'] ?? 0.0;
        unset($current['total']);

        $previous = $this->categoryFigures($criteria->previous());

        $categoryIds = [];

        foreach (array_keys($current) as $key) {
            if (str_starts_with($key, 'category:')) {
                $categoryIds[] = (int) substr($key, \strlen('category:'));
            }
        }

        $categories = $this->categories($categoryIds, $locale);

        /** @var array<int, list<int>> $children */
        $children = [];
        $roots = [];

        foreach ($categoryIds as $categoryId) {
            $parent = $categories[$categoryId]['parent'] ?? 0;

            if (0 !== $parent && isset($current['category:'.$parent])) {
                $children[$parent][] = $categoryId;
            } else {
                $roots[] = $categoryId;
            }
        }

        $byRevenue = static fn (int $a, int $b): int => [$current['category:'.$b]['revenue'], $a] <=> [$current['category:'.$a]['revenue'], $b];

        $rows = [];
        $visited = [];
        $walk = static function (int $categoryId, ?int $parentId, int $depth) use (&$walk, &$rows, &$visited, $children, $byRevenue, $current, $previous, $categories, $linesTotal): void {
            if (isset($visited[$categoryId])) {
                return;
            }

            $visited[$categoryId] = true;
            $figures = $current['category:'.$categoryId];

            $rows[] = new CategorySalesRow(
                CategoryRowKind::Category,
                $categoryId,
                $parentId,
                $categories[$categoryId]['title'] ?? '#'.$categoryId,
                $depth,
                $figures['revenue'],
                $figures['ownRevenue'],
                $figures['quantity'],
                $figures['orderCount'],
                self::share($figures['revenue'], $linesTotal),
                $previous['category:'.$categoryId]['revenue'] ?? 0.0,
            );

            $siblings = $children[$categoryId] ?? [];
            usort($siblings, $byRevenue);

            foreach ($siblings as $child) {
                $walk($child, $categoryId, $depth + 1);
            }
        };

        usort($roots, $byRevenue);

        foreach ($roots as $root) {
            $walk($root, null, 0);
        }

        // A parent loop in the data leaves categories no root leads to: they are listed at the top level.
        foreach ($categoryIds as $categoryId) {
            $walk($categoryId, null, 0);
        }

        foreach ([CategoryRowKind::Uncategorized, CategoryRowKind::Services] as $kind) {
            $figures = $current[$kind->value] ?? null;

            if (null === $figures) {
                continue;
            }

            $rows[] = new CategorySalesRow(
                $kind,
                null,
                null,
                $this->kindTitle($kind, $locale),
                0,
                $figures['revenue'],
                $figures['ownRevenue'],
                $figures['quantity'],
                $figures['orderCount'],
                self::share($figures['revenue'], $linesTotal),
                $previous[$kind->value]['revenue'] ?? 0.0,
            );
        }

        return $rows;
    }

    /**
     * @return SalesPage<ProductSalesRow> revenue descending, then reference
     */
    public function byProduct(SalesReportCriteria $criteria, string $locale, int $page = 1, int $perPage = 25): SalesPage
    {
        [$page, $perPage] = self::boundPage($page, $perPage);

        $sql = 'WITH '.$this->soldLineSql($criteria).'
            SELECT
                sold_line.product_ref AS ref,
                MAX(sold_line.product_id) AS product_id,
                MAX(sold_line.line_title) AS line_title,
                CAST(SUM(sold_line.line_total) AS DECIMAL(16,6)) AS revenue,
                SUM(sold_line.counted_quantity) AS quantity,
                COUNT(*) OVER () AS total_rows,
                CAST(SUM(SUM(sold_line.line_total)) OVER () AS DECIMAL(16,6)) AS lines_total
            FROM sold_line
            GROUP BY sold_line.product_ref
            HAVING '.self::SOLD_HAVING.'
            ORDER BY revenue DESC, ref ASC
            LIMIT '.$perPage.' OFFSET '.(($page - 1) * $perPage);

        $found = $this->fetchAll($sql, $criteria);

        if ([] === $found) {
            return new SalesPage([], $this->countSoldProducts($criteria), $page, $perPage);
        }

        $refs = array_map(static fn (array $row): string => (string) $row['ref'], $found);
        $previous = $this->productRevenues($criteria->previous(), $refs);
        $titles = $this->productTitles(
            array_values(array_filter(array_map(static fn (array $row): ?int => null === $row['product_id'] ? null : (int) $row['product_id'], $found))),
            $locale,
        );

        $rows = [];

        foreach ($found as $row) {
            $productId = null === $row['product_id'] ? null : (int) $row['product_id'];
            $ref = (string) $row['ref'];
            $revenue = (float) $row['revenue'];

            $rows[] = new ProductSalesRow(
                $productId,
                $ref,
                (null !== $productId ? $titles[$productId] ?? null : null) ?? (string) $row['line_title'],
                (int) round((float) $row['quantity']),
                $revenue,
                self::share($revenue, (float) $row['lines_total']),
                $previous[$ref] ?? 0.0,
            );
        }

        return new SalesPage($rows, (int) $found[0]['total_rows'], $page, $perPage);
    }

    /**
     * @return SalesPage<CustomerSalesRow> sorted on the given column, then customer id
     */
    public function byCustomer(SalesReportCriteria $criteria, CustomerSort $sort = CustomerSort::Total, bool $descending = true, int $page = 1, int $perPage = 25): SalesPage
    {
        [$page, $perPage] = self::boundPage($page, $perPage);
        $direction = $descending ? 'DESC' : 'ASC';

        $orderBy = match ($sort) {
            CustomerSort::Name => 'customer.lastname '.$direction.', customer.firstname '.$direction,
            CustomerSort::OrderCount => 'order_count '.$direction,
            CustomerSort::Total => 'total '.$direction,
            CustomerSort::AverageBasket => 'average_basket '.$direction,
            CustomerSort::LastOrder => 'last_order_at '.$direction,
        };

        $sql = 'WITH '.$this->placedOrderSql($criteria).'
            SELECT
                placed_order.customer_id,
                customer.ref,
                customer.firstname,
                customer.lastname,
                customer.email,
                COUNT(*) AS order_count,
                CAST(SUM('.OrderAmountSql::orderTotal('placed_order', 'placed_order.items_total').') AS DECIMAL(16,6)) AS total,
                CAST(SUM('.OrderAmountSql::orderTotal('placed_order', 'placed_order.items_total').') / COUNT(*) AS DECIMAL(16,6)) AS average_basket,
                MAX(placed_order.created_at) AS last_order_at,
                COUNT(*) OVER () AS total_rows
            FROM placed_order
            INNER JOIN customer ON customer.id = placed_order.customer_id
            GROUP BY placed_order.customer_id, customer.ref, customer.firstname, customer.lastname, customer.email
            ORDER BY '.$orderBy.', placed_order.customer_id ASC
            LIMIT '.$perPage.' OFFSET '.(($page - 1) * $perPage);

        $found = $this->fetchAll($sql, $criteria);

        if ([] === $found) {
            return new SalesPage([], $this->countCustomers($criteria), $page, $perPage);
        }

        $previous = $this->customerTotals(
            $criteria->previous(),
            array_map(static fn (array $row): int => (int) $row['customer_id'], $found),
        );

        $rows = [];

        foreach ($found as $row) {
            $customerId = (int) $row['customer_id'];

            $rows[] = new CustomerSalesRow(
                $customerId,
                (string) $row['ref'],
                (string) $row['firstname'],
                (string) $row['lastname'],
                (string) $row['email'],
                (int) $row['order_count'],
                (float) $row['total'],
                (float) $row['average_basket'],
                new \DateTimeImmutable((string) $row['last_order_at']),
                $previous[$customerId] ?? 0.0,
            );
        }

        return new SalesPage($rows, (int) $found[0]['total_rows'], $page, $perPage);
    }

    public function reconciliation(SalesReportCriteria $criteria): SalesReconciliation
    {
        $sql = 'WITH '.$this->placedOrderSql($criteria).'
            SELECT
                COALESCE(SUM(placed_order.items_total), 0) AS lines_total,
                COALESCE(SUM('.OrderAmountSql::appliedDiscount('placed_order', 'placed_order.items_total').'), 0) AS discounts,
                COALESCE(SUM(placed_order.postage), 0) AS postage,
                COUNT(*) AS order_count
            FROM placed_order';

        $row = $this->fetchAll($sql, $criteria)[0] ?? [];

        $linesTotal = (float) ($row['lines_total'] ?? 0);
        $discounts = (float) ($row['discounts'] ?? 0);
        $postage = (float) ($row['postage'] ?? 0);

        return new SalesReconciliation(
            $linesTotal,
            $discounts,
            $postage,
            $linesTotal - $discounts + $postage,
            (int) ($row['order_count'] ?? 0),
        );
    }

    /**
     * The figures of every category sold and of all its ancestors, of the uncategorized goods
     * and of the services, keyed `category:<id>`, `uncategorized` and `services`, plus the
     * total of the lines of the period under `total`.
     *
     * @return array<string, array{revenue: float, ownRevenue: float, quantity: int, orderCount: int}>
     */
    private function categoryFigures(SalesReportCriteria $criteria): array
    {
        $sql = 'WITH RECURSIVE '.$this->soldLineSql($criteria).',
            category_ancestor (category_id, ancestor_id, depth) AS (
                SELECT DISTINCT sold_line.category_id, sold_line.category_id, 0
                FROM sold_line
                WHERE sold_line.category_id IS NOT NULL
                UNION ALL
                SELECT category_ancestor.category_id, parent_category.id, category_ancestor.depth + 1
                FROM category_ancestor
                INNER JOIN category ON category.id = category_ancestor.ancestor_id
                INNER JOIN category AS parent_category ON parent_category.id = category.parent
                WHERE category_ancestor.depth < '.self::MAX_CATEGORY_DEPTH.'
            )
            SELECT
                CONCAT(\'category:\', category_ancestor.ancestor_id) AS figure_key,
                CAST(SUM(sold_line.line_total) AS DECIMAL(16,6)) AS revenue,
                CAST(SUM(CASE WHEN category_ancestor.depth = 0 THEN sold_line.line_total ELSE 0 END) AS DECIMAL(16,6)) AS own_revenue,
                SUM(sold_line.counted_quantity) AS quantity,
                COUNT(DISTINCT sold_line.counted_order_id) AS order_count
            FROM sold_line
            INNER JOIN category_ancestor ON category_ancestor.category_id = sold_line.category_id
            GROUP BY category_ancestor.ancestor_id
            HAVING '.self::SOLD_HAVING.'
            UNION ALL
            SELECT
                sold_line.kind,
                CAST(SUM(sold_line.line_total) AS DECIMAL(16,6)),
                CAST(SUM(sold_line.line_total) AS DECIMAL(16,6)),
                SUM(sold_line.counted_quantity),
                COUNT(DISTINCT sold_line.counted_order_id)
            FROM sold_line
            WHERE sold_line.category_id IS NULL
            GROUP BY sold_line.kind
            HAVING '.self::SOLD_HAVING.'
            UNION ALL
            SELECT
                \'total\',
                CAST(COALESCE(SUM(sold_line.line_total), 0) AS DECIMAL(16,6)),
                CAST(COALESCE(SUM(sold_line.line_total), 0) AS DECIMAL(16,6)),
                COALESCE(SUM(sold_line.counted_quantity), 0),
                COUNT(DISTINCT sold_line.counted_order_id)
            FROM sold_line';

        $figures = [];

        foreach ($this->fetchAll($sql, $criteria) as $row) {
            $figures[(string) $row['figure_key']] = [
                'revenue' => (float) $row['revenue'],
                'ownRevenue' => (float) $row['own_revenue'],
                'quantity' => (int) round((float) $row['quantity']),
                'orderCount' => (int) $row['order_count'],
            ];
        }

        return $figures;
    }

    /**
     * The lines of the orders of the period, one row each, with what the reports group them
     * by. `category_id` is null for a service and for goods no category holds; the quantity
     * and the order of a bundle component are null, so that it is not counted as sold.
     *
     * @param string $extraCondition a condition on `op` (the order line), with its own named parameters
     */
    private function soldLineSql(SalesReportCriteria $criteria, string $extraCondition = ''): string
    {
        $service = "'".OrderProduct::LINE_TYPE_SERVICE."'";
        $component = "'".OrderProduct::LINE_TYPE_BUNDLE_COMPONENT."'";

        return 'sold_line AS (
                SELECT
                    op.order_id,
                    op.product_ref,
                    op.title AS line_title,
                    product.id AS product_id,
                    CASE WHEN op.line_type = '.$service.' THEN NULL ELSE (
                        SELECT MIN(product_category.category_id)
                        FROM product_category
                        WHERE product_category.product_id = product.id AND product_category.default_category = 1
                    ) END AS category_id,
                    CASE WHEN op.line_type = '.$service.' THEN \''.CategoryRowKind::Services->value.'\' ELSE \''.CategoryRowKind::Uncategorized->value.'\' END AS kind,
                    CASE WHEN op.line_type = '.$component.' THEN 0 ELSE op.quantity END AS counted_quantity,
                    CASE WHEN op.line_type = '.$component.' THEN NULL ELSE op.order_id END AS counted_order_id,
                    '.OrderAmountSql::lineTaxedTotal('op', 'op.order_id').' AS line_total
                FROM order_product AS op
                INNER JOIN `order` AS o ON o.id = op.order_id
                LEFT JOIN product ON product.ref = op.product_ref
                WHERE '.$this->orderCondition($criteria).$extraCondition.'
            )';
    }

    /**
     * The orders of the period, one row each, with the taxed total of their goods computed once.
     */
    private function placedOrderSql(SalesReportCriteria $criteria, string $extraCondition = ''): string
    {
        return 'placed_order AS (
                SELECT
                    o.id,
                    o.customer_id,
                    o.created_at,
                    o.discount,
                    o.postage,
                    '.OrderAmountSql::itemsTaxedTotal('o').' AS items_total
                FROM `order` AS o
                WHERE '.$this->orderCondition($criteria).$extraCondition.'
            )';
    }

    /**
     * The orders of the criteria, on the alias `o`. The status and sale unit ids are written
     * in the statement as integers; the dates are the parameters `:from` and `:to`.
     */
    private function orderCondition(SalesReportCriteria $criteria): string
    {
        $condition = 'o.created_at >= :from AND o.created_at <= :to';

        if (StatusScope::Paid === $criteria->scope) {
            $condition .= ' AND '.self::inList('o.status_id', OrderStatusQuery::getPaidStatusIdList());
        }

        if (null !== $criteria->saleUnitIds) {
            $condition .= ' AND '.self::inList('o.sale_unit_id', $criteria->saleUnitIds);
        }

        return $condition;
    }

    /**
     * @param array<mixed> $ids
     */
    private static function inList(string $column, array $ids): string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return [] === $ids ? '1 = 0' : $column.' IN ('.implode(', ', $ids).')';
    }

    private function countSoldProducts(SalesReportCriteria $criteria): int
    {
        $sql = 'WITH '.$this->soldLineSql($criteria).'
            SELECT COUNT(*) AS total_rows FROM (
                SELECT sold_line.product_ref
                FROM sold_line
                GROUP BY sold_line.product_ref
                HAVING '.self::SOLD_HAVING.'
            ) AS sold_product';

        return (int) ($this->fetchAll($sql, $criteria)[0]['total_rows'] ?? 0);
    }

    private function countCustomers(SalesReportCriteria $criteria): int
    {
        $sql = 'SELECT COUNT(DISTINCT o.customer_id) AS total_rows FROM `order` AS o WHERE '.$this->orderCondition($criteria);

        return (int) ($this->fetchAll($sql, $criteria)[0]['total_rows'] ?? 0);
    }

    /**
     * @param list<string> $refs
     *
     * @return array<string, float>
     */
    private function productRevenues(SalesReportCriteria $criteria, array $refs): array
    {
        $parameters = [];

        foreach ($refs as $index => $ref) {
            $parameters['ref_'.$index] = $ref;
        }

        $sql = 'WITH '.$this->soldLineSql($criteria, ' AND op.product_ref IN (:'.implode(', :', array_keys($parameters)).')').'
            SELECT sold_line.product_ref AS ref, CAST(SUM(sold_line.line_total) AS DECIMAL(16,6)) AS revenue
            FROM sold_line
            GROUP BY sold_line.product_ref';

        $revenues = [];

        foreach ($this->fetchAll($sql, $criteria, $parameters) as $row) {
            $revenues[(string) $row['ref']] = (float) $row['revenue'];
        }

        return $revenues;
    }

    /**
     * @param list<int> $customerIds
     *
     * @return array<int, float>
     */
    private function customerTotals(SalesReportCriteria $criteria, array $customerIds): array
    {
        $sql = 'WITH '.$this->placedOrderSql($criteria, ' AND '.self::inList('o.customer_id', $customerIds)).'
            SELECT
                placed_order.customer_id,
                CAST(SUM('.OrderAmountSql::orderTotal('placed_order', 'placed_order.items_total').') AS DECIMAL(16,6)) AS total
            FROM placed_order
            GROUP BY placed_order.customer_id';

        $totals = [];

        foreach ($this->fetchAll($sql, $criteria) as $row) {
            $totals[(int) $row['customer_id']] = (float) $row['total'];
        }

        return $totals;
    }

    /**
     * The parent and the title of each category, in the locale asked for, else in another one.
     *
     * @param list<int> $categoryIds
     *
     * @return array<int, array{parent: int, title: string|null}>
     */
    private function categories(array $categoryIds, string $locale): array
    {
        if ([] === $categoryIds) {
            return [];
        }

        $sql = 'SELECT category.id, category.parent, category_i18n.locale, category_i18n.title
            FROM category
            LEFT JOIN category_i18n ON category_i18n.id = category.id
            WHERE '.self::inList('category.id', $categoryIds).'
            ORDER BY category.id, category_i18n.locale';

        $parents = [];
        $titles = [];

        foreach ($this->fetchAll($sql, null) as $row) {
            $id = (int) $row['id'];
            $parents[$id] = (int) $row['parent'];
            $titles[$id][(string) $row['locale']] = (string) $row['title'];
        }

        $categories = [];

        foreach ($parents as $id => $parent) {
            $categories[$id] = ['parent' => $parent, 'title' => self::pickTitle($titles[$id], $locale)];
        }

        return $categories;
    }

    /**
     * @param list<int> $productIds
     *
     * @return array<int, string|null>
     */
    private function productTitles(array $productIds, string $locale): array
    {
        if ([] === $productIds) {
            return [];
        }

        $sql = 'SELECT product_i18n.id, product_i18n.locale, product_i18n.title
            FROM product_i18n
            WHERE '.self::inList('product_i18n.id', $productIds).'
            ORDER BY product_i18n.id, product_i18n.locale';

        $titles = [];

        foreach ($this->fetchAll($sql, null) as $row) {
            $titles[(int) $row['id']][(string) $row['locale']] = (string) $row['title'];
        }

        return array_map(static fn (array $byLocale): ?string => self::pickTitle($byLocale, $locale), $titles);
    }

    /**
     * @param array<string, string> $byLocale
     */
    private static function pickTitle(array $byLocale, string $locale): ?string
    {
        if ('' !== trim($byLocale[$locale] ?? '')) {
            return $byLocale[$locale];
        }

        foreach ($byLocale as $title) {
            if ('' !== trim($title)) {
                return $title;
            }
        }

        return null;
    }

    private function kindTitle(CategoryRowKind $kind, string $locale): string
    {
        $label = CategoryRowKind::Services === $kind ? 'Services' : 'Uncategorized';

        try {
            return Translator::getInstance()->trans($label, [], 'core', $locale);
        } catch (\RuntimeException) {
            return $label;
        }
    }

    /**
     * @param array<string, scalar> $parameters
     *
     * @return list<array<string, mixed>>
     */
    private function fetchAll(string $sql, ?SalesReportCriteria $criteria, array $parameters = []): array
    {
        $statement = ($this->connection ?? Propel::getConnection())->prepare($sql);

        if (!$statement instanceof StatementInterface) {
            throw new \RuntimeException('The sales report statement could not be prepared.');
        }

        if ($criteria instanceof SalesReportCriteria) {
            $this->bindCriteria($statement, $sql, $criteria);
        }

        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value);
        }

        $statement->execute();

        $rows = [];

        while (false !== ($row = $statement->fetch(\PDO::FETCH_ASSOC))) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function bindCriteria(StatementInterface $statement, string $sql, SalesReportCriteria $criteria): void
    {
        $statement->bindValue('from', $criteria->from->format(self::SQL_DATE_FORMAT));
        $statement->bindValue('to', $criteria->to->format(self::SQL_DATE_FORMAT));

        if (str_contains($sql, ':'.OrderAmountSql::LEGACY_ROUNDING_PIVOT)) {
            OrderAmountSql::bindRoundingPivots($statement);
        }
    }

    /**
     * @return array{int, int}
     */
    private static function boundPage(int $page, int $perPage): array
    {
        return [min(self::MAX_PAGE, max(1, $page)), min(self::MAX_PER_PAGE, max(1, $perPage))];
    }

    private static function share(float $amount, float $total): float
    {
        return 0.0 === $total ? 0.0 : $amount / $total;
    }
}
