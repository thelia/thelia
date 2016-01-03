<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Thelia\Model\Base\CustomerTitle as BaseCustomerTitle;
use Thelia\Model\Map\CustomerTitleTableMap;

class CustomerTitle extends BaseCustomerTitle
{
    public function toggleDefault(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getConnection(CustomerTitleTableMap::DATABASE_NAME);
        }

        $con->beginTransaction();

        try {
            CustomerTitleQuery::create()
                ->update(array('ByDefault' => '0'))
            ;

            $this->setByDefault(1)->save();

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            throw $e;
        }
    }
}
