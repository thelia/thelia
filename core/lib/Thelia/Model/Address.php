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
use Thelia\Model\Base\Address as BaseAddress;

class Address extends BaseAddress
{
    /**
     * put the the current address as default one
     */
    public function makeItDefault()
    {
        AddressQuery::create()->filterByCustomerId($this->getCustomerId())
            ->update(['IsDefault' => '0']);

        $this->setIsDefault(1);
        $this->save();
    }

    /**
     * Code to be run before deleting the object in database
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
