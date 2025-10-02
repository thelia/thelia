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
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Customer\DTO\CustomerRegisterDTO;
use Thelia\Model\Customer;

readonly class CustomerUpdateService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function updateCustomer(
        CustomerRegisterDTO $customerRegisterDTO,
        Customer $customer,
    ): Customer {
        $customerChangeEvent = $this->updateEventInstance($customerRegisterDTO->toArray());
        $customerChangeEvent->setCustomer($customer);
        $this->eventDispatcher->dispatch($customerChangeEvent, TheliaEvents::CUSTOMER_UPDATEPROFILE);

        return $customer;
    }

    private function updateEventInstance(array $data): CustomerCreateOrUpdateEvent
    {
        return (new CustomerCreateOrUpdateEvent())->bindArray($data);
    }
}
