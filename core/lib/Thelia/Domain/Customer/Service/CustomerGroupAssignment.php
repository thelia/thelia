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

namespace Thelia\Domain\Customer\Service;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerGroupChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\Exception\UnknownCustomerGroupException;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroupQuery;

/**
 * The one way a customer is moved to another group, whoever asks: the back office, the API
 * administration, a module or a command.
 *
 * The group decides what the customer pays and sees, so a move is more than a column:
 * the group must exist, the memory the shop keeps of the old one for the request is dropped,
 * and {@see TheliaEvents::CUSTOMER_GROUP_CHANGED} tells whoever depends on it. The lines of the
 * carts the customer holds are not touched here: the checkout prices them again before it
 * writes an order, and refuses it when one moved.
 *
 * A customer put in a group at creation is not "moved": the group is written with the row
 * and nothing is raised. {@see assertGroupExists()} is what the creation asks.
 */
final readonly class CustomerGroupAssignment
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private CustomerGroupResolver $customerGroupResolver,
    ) {
    }

    /**
     * Puts a customer already stored in a group and writes it.
     *
     * @return bool whether the customer changed group: false, and nothing written nor raised,
     *              when it was in this group already
     *
     * @throws UnknownCustomerGroupException when the shop has no such group
     * @throws \LogicException               when the customer is not stored yet: a new one is created in its group
     */
    public function assign(Customer $customer, int $customerGroupId): bool
    {
        if ($customer->isNew()) {
            throw new \LogicException('A customer that is not stored yet is created in its group, not moved to it.');
        }

        $this->assertGroupExists($customerGroupId);

        $previousCustomerGroupId = (int) $customer->getCustomerGroupId();

        if ($previousCustomerGroupId === $customerGroupId) {
            return false;
        }

        $customer->setCustomerGroupId($customerGroupId)->save();

        $this->announce($customer, $previousCustomerGroupId);

        return true;
    }

    /**
     * Tells the shop that a customer was moved by a writer that already wrote the column (the
     * generic persistence of the API), when the group it carries now differs from the previous one.
     *
     * @return bool whether the customer changed group
     */
    public function announceChangeFrom(Customer $customer, int $previousCustomerGroupId): bool
    {
        if ((int) $customer->getCustomerGroupId() === $previousCustomerGroupId) {
            return false;
        }

        $this->announce($customer, $previousCustomerGroupId);

        return true;
    }

    /**
     * @throws UnknownCustomerGroupException
     */
    public function assertGroupExists(int $customerGroupId): void
    {
        if (!CustomerGroupQuery::create()->filterById($customerGroupId)->exists()) {
            throw UnknownCustomerGroupException::withId($customerGroupId);
        }
    }

    private function announce(Customer $customer, int $previousCustomerGroupId): void
    {
        $this->customerGroupResolver->forget((int) $customer->getId());

        $this->dispatcher->dispatch(
            new CustomerGroupChangedEvent($customer, $previousCustomerGroupId, (int) $customer->getCustomerGroupId()),
            TheliaEvents::CUSTOMER_GROUP_CHANGED,
        );
    }
}
