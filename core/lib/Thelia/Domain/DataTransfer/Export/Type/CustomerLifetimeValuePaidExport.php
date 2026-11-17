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

use Thelia\Domain\Report\Sales\StatusScope;

/**
 * The lifetime value of the cohorts of the paid orders only: the file of the back-office screen when it shows them.
 */
final class CustomerLifetimeValuePaidExport extends CustomerLifetimeValueExport
{
    public const FILE_NAME = 'customer_lifetime_value_paid';

    protected function scope(): StatusScope
    {
        return StatusScope::Paid;
    }
}
