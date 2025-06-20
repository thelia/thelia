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
namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Base\CustomerQuery as BaseCustomerQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'customer' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class CustomerQuery extends BaseCustomerQuery
{
    public static function getCustomerByEmail($email)
    {
        return self::create()->findOneByEmail($email);
    }

    public static function getMonthlyNewCustomersStats($month, $year)
    {
        $numberOfDay = cal_days_in_month(\CAL_GREGORIAN, $month, $year);

        $stats = [];
        for ($day = 1; $day <= $numberOfDay; ++$day) {
            $dayCustomers = self::create()
                        ->filterByCreatedAt(sprintf('%s-%s-%s 00:00:00', $year, $month, $day), Criteria::GREATER_EQUAL)
                        ->filterByCreatedAt(sprintf('%s-%s-%s 23:59:59', $year, $month, $day), Criteria::LESS_EQUAL)
                        ->count();
            $stats[] = [$day - 1, $dayCustomers];
        }

        return $stats;
    }
}

// CustomerQuery
