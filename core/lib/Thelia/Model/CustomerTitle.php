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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Model\Base\CustomerTitle as BaseCustomerTitle;
use Thelia\Model\Map\CustomerTitleTableMap;

class CustomerTitle extends BaseCustomerTitle
{
    public function toggleDefault(ConnectionInterface $con = null): void
    {
        if (null === $con) {
            $con = Propel::getConnection(CustomerTitleTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();

        try {
            CustomerTitleQuery::create()
                ->update(['ByDefault' => '0'])
            ;

            $this->setByDefault(1)->save();

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            throw $e;
        }
    }
}
