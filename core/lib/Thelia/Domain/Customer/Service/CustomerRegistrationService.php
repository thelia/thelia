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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateMinimalEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\DTO\CustomerRegisterDTO;
use Thelia\Domain\Customer\Exception\InvalidRegistrationTokenException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerTitleQuery;

readonly class CustomerRegistrationService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private CustomerGroupRegistration $customerGroupRegistration,
    ) {
    }

    /**
     * Registers an account in the default group, or in the group a registration token opens.
     *
     * The token is resolved here, before anything is written: the caller hands over what the
     * visitor sent, never a group. Without a token the account joins the default group.
     *
     * @throws InvalidRegistrationTokenException when a token is given and opens no group
     */
    public function registerCustomer(
        CustomerRegisterDTO $customerRegisterDTO,
        ?string $registrationToken = null,
    ): Customer {
        $group = null === $registrationToken ? null : $this->customerGroupRegistration->groupForToken($registrationToken);

        if (null !== $registrationToken && null === $group) {
            throw InvalidRegistrationTokenException::create();
        }

        $customerCreateEvent = $this->createEventInstance($customerRegisterDTO->toArray());

        if (null !== $group) {
            $customerCreateEvent->joinGroup((int) $group->getId());
        }

        $customerCreateEvent->setEnabled(false === ConfigQuery::isCustomerEmailConfirmationEnable());
        $this->eventDispatcher->dispatch($customerCreateEvent, TheliaEvents::CREATE_CUSTOMER_MINIMAL);
        $newCustomer = $customerCreateEvent->getCustomer();

        if (null === $newCustomer) {
            throw new \RuntimeException('Customer creation failed, no customer returned from event.');
        }

        return $newCustomer;
    }

    private function createEventInstance(array $data): CustomerCreateOrUpdateMinimalEvent
    {
        return (new CustomerCreateOrUpdateMinimalEvent())
            ->bindArray($data)
            ->setTitle(CustomerTitleQuery::create()
                ->filterByByDefault(1)
                ->findOne()->getId())
        ;
    }
}
