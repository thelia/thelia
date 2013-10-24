<?php

namespace Thelia\Model;

use Thelia\Model\Base\CustomerQuery as BaseCustomerQuery;


/**
 * Skeleton subclass for performing query and update operations on the 'customer' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class CustomerQuery extends BaseCustomerQuery {

    public static function getCustomerByEmail($email)
    {
        return self::create()->findOneByEmail($email);
    }
} // CustomerQuery
