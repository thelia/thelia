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

namespace Thelia\Domain\DataTransfer\Export\Type;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Domain\DataTransfer\Export\ReportSummaryLine;
use Thelia\Domain\Report\Sales\CategoryRowKind;
use Thelia\Domain\Report\Sales\CategorySalesRow;

/**
 * The paid sales of the period by category, the rows of the back-office screen in the same
 * order: a category, then its subcategories, each summing its own. The file ends with the
 * lines that bring the categories back to the revenue: the discounts, the shipping costs and
 * the total, so that the roots of the tree, the discounts and the shipping add up to it.
 */
class SalesByCategoryExport extends AbstractSalesReportExport
{
    public const FILE_NAME = 'sales_by_category';

    private const string PATH_SEPARATOR = ' > ';

    /** @var array<string, string> */
    protected array $orderAndAliases = [
        'category' => 'Category',
        'revenue' => 'Revenue incl. tax',
        'previous_revenue' => 'Previous period revenue incl. tax',
        'variation' => 'Change from the previous period (%)',
        'quantity' => 'Quantity',
        'orders' => 'Orders',
        'share' => 'Share of the revenue (%)',
    ];

    /**
     * @return list<array<string, string|int|float|null>>
     */
    protected function getData(): array|string|ModelCriteria
    {
        $criteria = $this->criteria();
        $calculator = $this->calculator();
        $categories = $calculator->byCategory($criteria, $this->locale());
        $reconciliation = $calculator->reconciliation($criteria);

        if ([] === $categories && 0 === $reconciliation->orderCount) {
            throw self::noData();
        }

        $rows = [];
        /** @var array<int, string> $paths */
        $paths = [];

        foreach ($categories as $category) {
            $path = self::pathOf($category, $paths);

            if (null !== $category->categoryId) {
                $paths[$category->categoryId] = $path;
            }

            $rows[] = [
                'category' => $path,
                'revenue' => self::amount($category->revenue),
                'previous_revenue' => self::amount($category->previousRevenue),
                'variation' => self::variation($category->revenue, $category->previousRevenue),
                'quantity' => $category->quantity,
                'orders' => $category->orderCount,
                'share' => self::percent($category->share),
            ];
        }

        $previous = $calculator->reconciliation($criteria->previous());

        $rows[] = self::reconciliationRow($this->label('Discounts'), -$reconciliation->discounts);
        $rows[] = self::reconciliationRow($this->label('Shipping costs'), $reconciliation->postage);
        $rows[] = [
            'category' => $this->label('Total'),
            'revenue' => self::amount($reconciliation->revenue),
            'previous_revenue' => self::amount($previous->revenue),
            'variation' => self::variation($reconciliation->revenue, $previous->revenue),
            'quantity' => null,
            'orders' => $reconciliation->orderCount,
            'share' => null,
        ];

        return $rows;
    }

    /**
     * The revenue of the period, its change against the previous period, and the category
     * that sold the most.
     *
     * @return list<ReportSummaryLine>
     */
    public function summarize(): array
    {
        $criteria = $this->criteria();
        $lines = $this->revenueLines($criteria);
        $top = $this->calculator()->byCategory($criteria, $this->locale())[0] ?? null;

        if ($top instanceof CategorySalesRow && CategoryRowKind::Category === $top->kind) {
            $lines[] = new ReportSummaryLine($this->label('Top category'), $top->title.' ('.$this->money($top->revenue).')');
        }

        return $lines;
    }

    /**
     * @return array<string, string|int|float|null>
     */
    private static function reconciliationRow(string $label, float $amount): array
    {
        return [
            'category' => $label,
            'revenue' => self::amount($amount),
            'previous_revenue' => null,
            'variation' => null,
            'quantity' => null,
            'orders' => null,
            'share' => null,
        ];
    }

    /**
     * "Parent > Child": the path of the category, from its ancestors listed before it.
     *
     * @param array<int, string> $paths
     */
    private static function pathOf(CategorySalesRow $category, array $paths): string
    {
        if (null === $category->parentId || !isset($paths[$category->parentId])) {
            return $category->title;
        }

        return $paths[$category->parentId].self::PATH_SEPARATOR.$category->title;
    }
}
