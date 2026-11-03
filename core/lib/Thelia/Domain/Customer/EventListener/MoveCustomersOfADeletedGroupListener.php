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

namespace Thelia\Domain\Customer\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Domain\Customer\Service\CustomerGroupAssignment;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Event\CustomerGroupEvent;

/**
 * A group that goes away hands its customers to the default group, and each of them is
 * moved as any other move is: through {@see CustomerGroupAssignment}, which writes the
 * customer, drops what the shop remembers of its old group and announces the move. It runs
 * in the transaction of the deletion, before the row goes.
 *
 * The default group itself is never deleted ({@see CustomerGroup::preDelete()} refuses it
 * before this runs).
 */
final readonly class MoveCustomersOfADeletedGroupListener
{
    public function __construct(
        private CustomerGroupAssignment $customerGroupAssignment,
    ) {
    }

    #[AsEventListener(event: CustomerGroupEvent::PRE_DELETE)]
    public function moveCustomersToTheDefaultGroup(CustomerGroupEvent $event): void
    {
        $customerGroup = $event->getModel();

        if (!$customerGroup instanceof CustomerGroup || 1 === $customerGroup->getIsDefault()) {
            return;
        }

        $defaultCustomerGroupId = (int) CustomerGroupQuery::findDefault()->getId();

        foreach (CustomerQuery::create()->filterByCustomerGroupId($customerGroup->getId())->find() as $customer) {
            $this->customerGroupAssignment->assign($customer, $defaultCustomerGroupId);
        }
    }
}
