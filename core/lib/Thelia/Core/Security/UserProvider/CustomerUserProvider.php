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

namespace Thelia\Core\Security\UserProvider;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\CustomerQuery;

class CustomerUserProvider implements UserProviderInterface
{
    public function getUser($key)
    {
        $customer = CustomerQuery::create()
            ->filterByEmail($key, Criteria::EQUAL)
            ->findOne();

        return $customer;
    }
}
