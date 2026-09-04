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
    /**
     * The account registered on an address, if there is one.
     *
     * Guest rows are not accounts and never come back from here. The checkout writes one
     * for whoever types an address, so a guest row proves nothing about who owns the
     * address: it must not stand in the way of that address being registered, and it must
     * not be handed to anything that means "the customer behind this address". A guest
     * that chose a password is still a guest row until its activation code is answered.
     */
    public static function getCustomerByEmail($email): ?Customer
    {
        return self::create()
            ->filterByEmail($email)
            ->filterByIsGuest(0)
            ->findOne();
    }

    public static function getMonthlyNewCustomersStats($month, $year)
    {
        $numberOfDay = cal_days_in_month(\CAL_GREGORIAN, $month, $year);

        $stats = [];

        for ($day = 1; $day <= $numberOfDay; ++$day) {
            $dayCustomers = self::create()
                ->filterByCreatedAt(\sprintf('%s-%s-%s 00:00:00', $year, $month, $day), Criteria::GREATER_EQUAL)
                ->filterByCreatedAt(\sprintf('%s-%s-%s 23:59:59', $year, $month, $day), Criteria::LESS_EQUAL)
                ->count();
            $stats[] = [$day - 1, $dayCustomers];
        }

        return $stats;
    }
}

// CustomerQuery
