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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Customer\Exception\DefaultCustomerGroupProtectedException;
use Thelia\Model\Base\CustomerGroup as BaseCustomerGroup;
use Thelia\Model\Map\CustomerGroupTableMap;
use Thelia\Model\Map\CustomerTableMap;

class CustomerGroup extends BaseCustomerGroup
{
    /**
     * The discount rate the prices apply for the members of the group, in %.
     *
     * A rate outside 0..100 (0 excluded), which only a direct write to the database
     * can leave, counts for nothing rather than being capped: it never raises a price
     * nor gives the goods away.
     */
    public function effectiveDiscountRate(): float
    {
        // getDiscount() maps a DECIMAL column and returns a string.
        $rate = (float) $this->getDiscount();

        return $rate > 0.0 && $rate <= 100.0 ? $rate : 0.0;
    }

    /**
     * Exactly one group is the default: flagging this one releases the previous one.
     */
    public function preSave(?ConnectionInterface $con = null): bool
    {
        if (1 === $this->getIsDefault() && ($this->isNew() || $this->isColumnModified(CustomerGroupTableMap::COL_IS_DEFAULT))) {
            $others = CustomerGroupQuery::create()->filterByIsDefault(1);

            if (!$this->isNew()) {
                $others->filterById($this->getId(), Criteria::NOT_EQUAL);
            }

            $others->update(['IsDefault' => 0], $con);

            // The default group is where an account lands without a link: it keeps none.
            $this->setRegistrationToken(null);
        }

        return parent::preSave($con);
    }

    /**
     * The shop is left with no default group when the only one stops being it.
     */
    public function preUpdate(?ConnectionInterface $con = null): bool
    {
        if (0 === $this->getIsDefault() && $this->isColumnModified(CustomerGroupTableMap::COL_IS_DEFAULT)) {
            $otherDefaults = CustomerGroupQuery::create()
                ->filterByIsDefault(1)
                ->filterById($this->getId(), Criteria::NOT_EQUAL)
                ->count($con);

            if (0 === $otherDefaults) {
                throw DefaultCustomerGroupProtectedException::onUnflagging((int) $this->getId());
            }
        }

        return parent::preUpdate($con);
    }

    /**
     * The default group cannot go. Any other one hands its customers to the default group
     * first, in the transaction of the deletion: `customer.customer_group_id` is RESTRICT, and
     * a customer left with no group is not a state the shop has. Its lists of categories and
     * payment modules go with it (ON DELETE CASCADE): they were its own, and no other group
     * sees more or less for it.
     *
     * @throws DefaultCustomerGroupProtectedException
     */
    public function preDelete(?ConnectionInterface $con = null): bool
    {
        if (1 === $this->getIsDefault()) {
            throw DefaultCustomerGroupProtectedException::onDeletion((int) $this->getId());
        }

        CustomerQuery::create()
            ->filterByCustomerGroupId($this->getId())
            ->update(['CustomerGroupId' => CustomerGroupQuery::findDefault()->getId()], $con);

        // A bulk update leaves the pooled customers on the group that is going away.
        CustomerTableMap::clearInstancePool();

        return parent::preDelete($con);
    }
}
