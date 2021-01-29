<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\Address as BaseAddress;

class Address extends BaseAddress
{
    /**
     * put the the current address as default one
     */
    public function makeItDefault()
    {
        AddressQuery::create()->filterByCustomerId($this->getCustomerId())
            ->update(array('IsDefault' => '0'));

        $this->setIsDefault(1);
        $this->save();
    }

    /**
     * Code to be run before deleting the object in database
     * @param ConnectionInterface|null $con
     * @return boolean
     */
    public function preDelete(ConnectionInterface $con = null): bool
    {
        parent::preDelete($con);

        if ($this->getIsDefault()) {
            return false;
        }

        return true;
    }
}
