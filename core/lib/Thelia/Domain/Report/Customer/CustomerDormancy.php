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

namespace Thelia\Domain\Report\Customer;

use Thelia\Model\ConfigQuery;

/**
 * The number of days without an order after which a customer is dormant: the shop setting
 * `customer_dormant_after_days`, 180 when it is missing or not a positive number of days.
 */
final class CustomerDormancy
{
    public const string CONFIG_KEY = 'customer_dormant_after_days';

    public const int DEFAULT_DAYS = 180;

    public static function days(): int
    {
        $days = filter_var(ConfigQuery::read(self::CONFIG_KEY, self::DEFAULT_DAYS), \FILTER_VALIDATE_INT);

        return \is_int($days) && $days > 0 ? $days : self::DEFAULT_DAYS;
    }
}
