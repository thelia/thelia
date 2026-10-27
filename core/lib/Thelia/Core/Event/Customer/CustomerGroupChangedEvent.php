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

namespace Thelia\Core\Event\Customer;

use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Model\Customer;

/**
 * A customer was moved to another group, and the move is written.
 *
 * Raised by {@see \Thelia\Domain\Customer\Service\CustomerGroupAssignment}, for the back
 * office, the API and a module alike: what depends on the group of a customer (a price list
 * a module keeps, a mailing segment, a loyalty tier) listens here instead of watching the
 * column.
 */
final class CustomerGroupChangedEvent extends Event
{
    public function __construct(
        private readonly Customer $customer,
        private readonly int $previousCustomerGroupId,
        private readonly int $customerGroupId,
    ) {
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getPreviousCustomerGroupId(): int
    {
        return $this->previousCustomerGroupId;
    }

    public function getCustomerGroupId(): int
    {
        return $this->customerGroupId;
    }
}
