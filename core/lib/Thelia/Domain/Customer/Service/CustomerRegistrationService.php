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
use Thelia\Model\Customer;
use Thelia\Model\CustomerTitleQuery;

readonly class CustomerRegistrationService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function registerCustomer(
        CustomerRegisterDTO $customerRegisterDTO,
    ): Customer {
        $customerCreateEvent = $this->createEventInstance($customerRegisterDTO->toArray());
        $customerCreateEvent->setEnabled(false);
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
