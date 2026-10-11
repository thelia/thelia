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

use Thelia\Model\Customer;
use Thelia\Model\CustomerList;
use Thelia\Model\CustomerListQuery;

/**
 * Who sees, changes and shares a purchase list. The only place those rules
 * live: the facade, the API and the theme all ask here.
 *
 * Customers cannot belong to a company yet, so a list is personal: its owner
 * reads and writes it, and nobody shares it. Company sharing (reading the
 * shared lists of colleagues, writing for the company administrator) plugs in
 * here and nowhere else.
 */
final readonly class PurchaseListAccessPolicy
{
    public function restrictToReadable(CustomerListQuery $query, Customer $customer): CustomerListQuery
    {
        return $query->filterByCustomerId((int) $customer->getId());
    }

    public function canRead(Customer $customer, CustomerList $list): bool
    {
        return $this->isOwner($customer, $list);
    }

    public function canWrite(Customer $customer, CustomerList $list): bool
    {
        return $this->isOwner($customer, $list);
    }

    public function canShare(Customer $customer, CustomerList $list): bool
    {
        return false;
    }

    private function isOwner(Customer $customer, CustomerList $list): bool
    {
        return (int) $list->getCustomerId() === (int) $customer->getId();
    }
}
