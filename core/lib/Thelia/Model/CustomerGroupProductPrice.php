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

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\CustomerGroupProductPrice as BaseCustomerGroupProductPrice;

class CustomerGroupProductPrice extends BaseCustomerGroupProductPrice
{
    /**
     * A row with neither a price nor a promo price says nothing: the catalog applies to the
     * group as if it were not there, and keeping it would only make every reader of the
     * prices of a group go through it. It is dropped by whichever path writes it - the API,
     * the back office, a module - so that none of them can leave one behind.
     */
    public function preSave(?ConnectionInterface $con = null): bool
    {
        if (null === $this->getPrice() && null === $this->getPromoPrice()) {
            if (!$this->isNew()) {
                $this->delete($con);
            }

            return false;
        }

        return parent::preSave($con);
    }
}
