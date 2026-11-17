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

use Thelia\Domain\Report\Customer\CohortGranularity;
use Thelia\Domain\Report\Customer\CustomerLifetimeCalculator;

/**
 * What the customer exports share with the sales exports (the period of the job, the orders
 * counted, the sale units of the administrator), plus the unit of time of their cohorts.
 *
 * For the cohort and lifetime value exports the period is the one of the first orders: a customer
 * whose first order falls in it is followed from that order up to its end.
 */
abstract class AbstractCustomerReportExport extends AbstractSalesReportExport
{
    protected function lifetimeCalculator(): CustomerLifetimeCalculator
    {
        return new CustomerLifetimeCalculator();
    }

    protected function granularity(): CohortGranularity
    {
        return CohortGranularity::Month;
    }

    /**
     * The heading of the column of a number of months (or quarters) since the first order.
     */
    protected function elapsedLabel(int $elapsed): string
    {
        return \Thelia\Core\Translation\Translator::getInstance()->trans(
            CohortGranularity::Quarter === $this->granularity() ? 'Quarter %number%' : 'Month %number%',
            ['%number%' => $elapsed],
            'core',
            $this->locale(),
        );
    }
}
