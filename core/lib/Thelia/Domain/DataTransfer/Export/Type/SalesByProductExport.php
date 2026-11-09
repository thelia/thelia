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

/**
 * The paid sales of the period by product, best seller first: every product sold, the pages
 * of the back-office screen put end to end.
 */
class SalesByProductExport extends AbstractSalesReportExport
{
    public const FILE_NAME = 'sales_by_product';

    private const int PER_PAGE = 500;

    /** @var array<string, string> */
    protected array $orderAndAliases = [
        'reference' => 'Product reference',
        'title' => 'Product',
        'revenue' => 'Revenue incl. tax',
        'previous_revenue' => 'Previous period revenue incl. tax',
        'variation' => 'Change from the previous period (%)',
        'quantity' => 'Quantity',
        'share' => 'Share of the revenue (%)',
    ];

    /**
     * @return list<array<string, string|int|float|null>>
     */
    protected function getData(): array|string|ModelCriteria
    {
        $criteria = $this->criteria();
        $calculator = $this->calculator();
        $rows = [];
        $page = 1;

        do {
            $products = $calculator->byProduct($criteria, $this->locale(), $page, self::PER_PAGE);

            foreach ($products->rows as $product) {
                $rows[] = [
                    'reference' => $product->ref,
                    'title' => $product->title,
                    'revenue' => self::amount($product->revenue),
                    'previous_revenue' => self::amount($product->previousRevenue),
                    'variation' => self::variation($product->revenue, $product->previousRevenue),
                    'quantity' => $product->quantity,
                    'share' => self::percent($product->share),
                ];
            }
        } while ($page++ < $products->pageCount());

        if ([] === $rows) {
            throw self::noData();
        }

        return $rows;
    }

    /**
     * The revenue of the period, its change against the previous period, and the best seller.
     *
     * @return list<ReportSummaryLine>
     */
    public function summarize(): array
    {
        $criteria = $this->criteria();
        $lines = $this->revenueLines($criteria);
        $top = $this->calculator()->byProduct($criteria, $this->locale(), 1, 1)->rows[0] ?? null;

        if (null !== $top) {
            $lines[] = new ReportSummaryLine($this->label('Top product'), $top->title.' ('.$this->money($top->revenue).')');
        }

        return $lines;
    }
}
