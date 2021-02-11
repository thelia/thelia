<?php

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
use Thelia\Model\Base\AdminLogQuery as BaseAdminLogQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'admin_log' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class AdminLogQuery extends BaseAdminLogQuery
{
    /**
     * @param null $login
     * @param null $minDate
     * @param null $maxDate
     * @param null $resources
     * @param null $actions
     *
     * @return array|mixed|\Propel\Runtime\Collection\ObjectCollection
     */
    public static function getEntries($login = null, $minDate = null, $maxDate = null, $resources = null, $actions = null)
    {
        $search = self::create();

        if (null !== $minDate) {
            $search->filterByCreatedAt($minDate, Criteria::GREATER_EQUAL);
        }

        if (null !== $maxDate) {
            $maxDateObject = new \DateTime($maxDate);
            $maxDateObject->add(new \DateInterval('P1D'));
            $search->filterByCreatedAt(date('Y-m-d', $maxDateObject->getTimestamp()), Criteria::LESS_THAN);
        }

        if (null !== $resources) {
            $search->filterByResource($resources);
        }

        if (null !== $actions) {
            $search->filterByAction($actions);
        }

        if (null !== $login) {
            $search->filterByAdminLogin($login);
        }

        return $search->find();
    }
}
// AdminLogQuery
