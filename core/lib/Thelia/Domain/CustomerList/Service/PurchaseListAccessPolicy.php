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

namespace Thelia\Domain\CustomerList\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Model\Customer;
use Thelia\Model\CustomerList;
use Thelia\Model\CustomerListQuery;
use Thelia\Model\Map\CustomerListTableMap;

/**
 * Who sees, changes and shares a purchase list. The only place those rules
 * live: the facade, the API and the theme all ask here.
 *
 * A list is its owner's: the owner reads and writes it. A list its owner shares is read, and only
 * read, by every buyer of the company the owner belongs to: a colleague changes nothing in it.
 * An owner who belongs to no company, or who left it, shares with nobody.
 */
final readonly class PurchaseListAccessPolicy
{
    public function __construct(private CompanyMembershipReader $membershipReader)
    {
    }

    /**
     * The lists of the customer and, for a buyer of a company, the shared lists of the buyers of
     * that company, in the one query.
     */
    public function restrictToReadable(CustomerListQuery $query, Customer $customer): CustomerListQuery
    {
        $membership = $this->membershipReader->membershipOf($customer);

        if (null === $membership) {
            return $query->filterByCustomerId((int) $customer->getId());
        }

        return $query
            ->condition('own', CustomerListTableMap::COL_CUSTOMER_ID.' = ?', (int) $customer->getId(), \PDO::PARAM_INT)
            ->condition(
                'shared',
                \sprintf(
                    '%s = 1 AND %s IN (SELECT `company_customer`.`customer_id` FROM `company_customer` WHERE `company_customer`.`company_id` = ?)',
                    CustomerListTableMap::COL_SHARED,
                    CustomerListTableMap::COL_CUSTOMER_ID,
                ),
                $membership->companyId,
                \PDO::PARAM_INT,
            )
            ->where(['own', 'shared'], Criteria::LOGICAL_OR);
    }

    public function canRead(Customer $customer, CustomerList $list): bool
    {
        if ($this->isOwner($customer, $list)) {
            return true;
        }

        if (1 !== (int) $list->getShared()) {
            return false;
        }

        $companyId = $this->membershipReader->membershipOf($customer)?->companyId;

        return null !== $companyId && $this->membershipReader->membershipOf((int) $list->getCustomerId())?->companyId === $companyId;
    }

    public function canWrite(Customer $customer, CustomerList $list): bool
    {
        return $this->isOwner($customer, $list);
    }

    /**
     * Only the owner shares a list, and only with a company they belong to.
     */
    public function canShare(Customer $customer, CustomerList $list): bool
    {
        return $this->isOwner($customer, $list) && null !== $this->membershipReader->membershipOf($customer);
    }

    private function isOwner(Customer $customer, CustomerList $list): bool
    {
        return (int) $list->getCustomerId() === (int) $customer->getId();
    }
}
