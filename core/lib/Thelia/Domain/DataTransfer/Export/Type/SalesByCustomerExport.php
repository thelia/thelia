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
use Thelia\Domain\Report\Sales\CustomerSort;

/**
 * The paid sales of the period by customer, the biggest total first: every customer who
 * ordered, the pages of the back-office screen put end to end.
 */
class SalesByCustomerExport extends AbstractSalesReportExport
{
    public const FILE_NAME = 'sales_by_customer';

    private const int PER_PAGE = 500;

    /** @var array<string, string> */
    protected array $orderAndAliases = [
        'reference' => 'Customer reference',
        'lastname' => 'Last name',
        'firstname' => 'First name',
        'email' => 'Email address',
        'orders' => 'Orders',
        'total' => 'Total incl. tax',
        'average_basket' => 'Average basket incl. tax',
        'last_order' => 'Last order',
        'previous_total' => 'Previous period total incl. tax',
        'variation' => 'Change from the previous period (%)',
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
            $customers = $calculator->byCustomer($criteria, CustomerSort::Total, true, $page, self::PER_PAGE);

            foreach ($customers->rows as $customer) {
                $rows[] = [
                    'reference' => $customer->ref,
                    'lastname' => $customer->lastname,
                    'firstname' => $customer->firstname,
                    'email' => $customer->email,
                    'orders' => $customer->orderCount,
                    'total' => self::amount($customer->total),
                    'average_basket' => self::amount($customer->averageBasket),
                    'last_order' => $customer->lastOrderAt->format('Y-m-d H:i:s'),
                    'previous_total' => self::amount($customer->previousTotal),
                    'variation' => self::variation($customer->total, $customer->previousTotal),
                ];
            }
        } while ($page++ < $customers->pageCount());

        if ([] === $rows) {
            throw self::noData();
        }

        return $rows;
    }

    /**
     * The revenue of the period, its change against the previous period, and the best customer.
     *
     * @return list<ReportSummaryLine>
     */
    public function summarize(): array
    {
        $criteria = $this->criteria();
        $lines = $this->revenueLines($criteria);
        $top = $this->calculator()->byCustomer($criteria, CustomerSort::Total, true, 1, 1)->rows[0] ?? null;

        if (null !== $top) {
            $lines[] = new ReportSummaryLine($this->label('Top customer'), trim($top->firstname.' '.$top->lastname).' ('.$this->money($top->total).')');
        }

        return $lines;
    }
}
