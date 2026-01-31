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

namespace Thelia\Domain\Addressing;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Addressing\DTO\AddressDTO;
use Thelia\Domain\Addressing\Exception\AddressNotFoundException;
use Thelia\Domain\Customer\Exception\CustomerException;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Customer;
use Thelia\Model\Event\AddressEvent;

final readonly class AddressFacade
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private Session $session,
    ) {
    }

    public function create(AddressDTO $dto, ?Customer $customer = null): Address
    {
        $customer ??= $this->session->getCustomerUser();

        if (null === $customer) {
            throw new CustomerException('No customer provided and no customer in session');
        }

        $event = $this->createEventFromDTO($dto);
        $event->setCustomer($customer);

        $this->dispatcher->dispatch($event, TheliaEvents::ADDRESS_CREATE);

        return $event->getAddress();
    }

    public function update(int $addressId, AddressDTO $dto): Address
    {
        $address = $this->getById($addressId);

        if (null === $address) {
            throw new AddressNotFoundException();
        }

        $this->assertCustomerOwnership($address);

        $event = $this->createEventFromDTO($dto);
        $event->setAddress($address);

        $this->dispatcher->dispatch($event, TheliaEvents::ADDRESS_UPDATE);

        return $event->getAddress();
    }

    public function delete(int $addressId): void
    {
        $address = $this->getById($addressId);

        if (null === $address) {
            throw new AddressNotFoundException();
        }

        $this->assertCustomerOwnership($address);

        $this->dispatcher->dispatch(new AddressEvent($address), TheliaEvents::ADDRESS_DELETE);
    }

    public function setAsDefault(int $addressId): void
    {
        $address = $this->getById($addressId);

        if (null === $address) {
            throw new AddressNotFoundException();
        }

        $this->assertCustomerOwnership($address);

        $this->dispatcher->dispatch(new AddressEvent($address), TheliaEvents::ADDRESS_DEFAULT);
    }

    public function getById(int $addressId): ?Address
    {
        return AddressQuery::create()->findPk($addressId);
    }

    public function getByCustomer(?Customer $customer = null): array
    {
        $customer ??= $this->session->getCustomerUser();

        if (null === $customer) {
            return [];
        }

        return AddressQuery::create()
            ->filterByCustomerId($customer->getId())
            ->orderByIsDefault('DESC')
            ->orderByLabel()
            ->find()
            ->getData();
    }

    public function getDefault(?Customer $customer = null): ?Address
    {
        $customer ??= $this->session->getCustomerUser();

        if (null === $customer) {
            return null;
        }

        return $customer->getDefaultAddress();
    }

    private function createEventFromDTO(AddressDTO $dto): AddressCreateOrUpdateEvent
    {
        return new AddressCreateOrUpdateEvent(
            $dto->label,
            $dto->titleId,
            $dto->firstname,
            $dto->lastname,
            $dto->address1,
            $dto->address2,
            $dto->address3 ?? '',
            $dto->zipcode,
            $dto->city,
            $dto->countryId,
            $dto->cellphone,
            $dto->phone,
            $dto->company,
            $dto->isDefault,
            $dto->stateId,
        );
    }

    private function assertCustomerOwnership(Address $address): void
    {
        $customer = $this->session->getCustomerUser();

        if (null === $customer || $address->getCustomerId() !== $customer->getId()) {
            throw new CustomerException('Address does not belong to current customer');
        }
    }
}
