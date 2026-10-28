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

namespace Thelia\Tests\Support\Pricing;

use Thelia\Domain\Pricing\CustomerPrice\CustomerDiscountRate;
use Thelia\Model\Customer;

/**
 * The discount rate of the customer in memory, for the unit tests that have no database to
 * read it back from. The rule on the rate is the one of the service.
 */
final class LoadedCustomerDiscountRate extends CustomerDiscountRate
{
    public function rateOf(?Customer $customer): float
    {
        return null === $customer ? 0.0 : self::effectiveRate($customer->getDiscount());
    }
}
