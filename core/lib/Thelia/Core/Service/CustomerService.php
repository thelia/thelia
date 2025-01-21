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

namespace Thelia\Core\Service;

use Thelia\Model\CustomerTitleQuery;

class CustomerService
{
    public function getDefaultCustomerTitle()
    {
        return CustomerTitleQuery::create()
            ->filterByByDefault(1)
            ->limit(1)
            ->findOne();
    }
}
