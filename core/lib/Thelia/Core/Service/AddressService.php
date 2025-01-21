<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Service;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Exception\AddressNotFoundException;
use Thelia\Exception\CustomerException;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Customer;
use Thelia\Model\Event\AddressEvent;

class AddressService
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly Session $session
    ) {
    }

    public function mapModelToFormData(Address $address): array
    {
        return [
            'label' => $address->getLabel(),
            'firstname' => $address->getFirstname(),
            'lastname' => $address->getLastname(),
            'company' => $address->getCompany(),
            'address1' => $address->getAddress1(),
            'address2' => $address->getAddress2(),
            'address3' => $address->getAddress3(),
            'city' => $address->getCity(),
            'zipcode' => $address->getZipcode(),
            'country' => $address->getCountryId(),
            'state' => $address->getStateId(),
            'phone' => $address->getPhone(),
            'is_default' => (bool) $address->getIsDefault(),
        ];
    }

    /**
     * @throws PropelException|AddressNotFoundException
     */
    public function updateOrCreateAddress(?int $addressId, FormInterface $form): void
    {
        if ($addressId) {
            $this->updateAddress($addressId, $form);

            return;
        }
        $this->createAddress($form);
    }

    /**
     * @throws AddressNotFoundException|CustomerException|PropelException
     */
    public function updateAddress(int $addressId, FormInterface $form): void
    {
        $customer = $this->session->getCustomerUser();

        $address = AddressQuery::create()->findPk($addressId);

        if (null === $address) {
            throw new AddressNotFoundException();
        }

        if ($address->getCustomer()->getId() != $customer->getId()) {
            throw new CustomerException();
        }

        $event = $this->createAddressEvent($form);
        $event->setAddress($address);

        $this->dispatcher->dispatch($event, TheliaEvents::ADDRESS_UPDATE);
    }

    public function createAddress(FormInterface $form): void
    {
        /** @var Customer $customer */
        $customer = $this->session->getCustomerUser();

        $event = $this->createAddressEvent($form);
        $event->setCustomer($customer);

        $this->dispatcher->dispatch($event, TheliaEvents::ADDRESS_CREATE);
    }

    /**
     * @throws PropelException|AddressNotFoundException|CustomerException
     */
    public function deleteAddress(int $addressId): void
    {
        $customer = $this->session->getCustomerUser();
        $address = AddressQuery::create()->findPk($addressId);
        if (null === $address) {
            throw new AddressNotFoundException();
        }

        if ($address->getCustomer()->getId() != $customer->getId()) {
            throw new CustomerException();
        }

        $addressEvent = new AddressEvent($address);
        $this->dispatcher->dispatch($addressEvent, TheliaEvents::ADDRESS_DELETE);
    }

    protected function createAddressEvent(FormInterface $form): AddressCreateOrUpdateEvent
    {
        $data = $form->getData();

        return new AddressCreateOrUpdateEvent(
            $data['label'],
            $data['title'] ?? null,
            $data['firstname'],
            $data['lastname'],
            $data['address1'],
            $data['address2'],
            $data['address3'] ?? '',
            $data['zipcode'],
            $data['city'],
            $data['country'],
            $data['cellphone'] ?? null,
            $data['phone'],
            $data['company'] ?? null,
            $data['is_default'] ?? false,
            $data['state'] ?? null
        );
    }
}
