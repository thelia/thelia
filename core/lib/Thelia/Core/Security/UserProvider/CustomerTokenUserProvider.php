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

class CustomerTokenUserProvider extends TokenUserProvider
{
    public function getUser($dataArray)
    {
        return CustomerQuery::create()
            ->filterByEmail($dataArray['username'], Criteria::EQUAL)
            ->filterByRememberMeSerial($dataArray['serial'], Criteria::EQUAL)
            ->filterByRememberMeToken($dataArray['token'], Criteria::EQUAL)
            ->findOne();
    }
}
