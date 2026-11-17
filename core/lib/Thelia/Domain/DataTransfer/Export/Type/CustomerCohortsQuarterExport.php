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

/**
 * The cohort table by quarter of first order: the file of the back-office screen when it shows them.
 */
class CustomerCohortsQuarterExport extends CustomerCohortsExport
{
    public const FILE_NAME = 'customer_cohorts_quarter';

    protected function granularity(): CohortGranularity
    {
        return CohortGranularity::Quarter;
    }
}
