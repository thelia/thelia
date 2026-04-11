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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\AddressQuery;
use Thelia\Model\Event\AddressEvent;
use Thelia\Test\ActionIntegrationTestCase;

final class AddressActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsAddressForCustomer(): void
    {
        $title = $this->factory->customerTitle();
        $customer = $this->factory->customer($title);
        $country = $this->factory->country();

        $event = new AddressCreateOrUpdateEvent(
            label: 'Home',
            title: $title->getId(),
            firstname: 'John',
            lastname: 'Doe',
            address1: '10 rue de la Paix',
            address2: '',
            address3: '',
            zipcode: '75002',
            city: 'Paris',
            country: $country->getId(),
            cellphone: '',
            phone: '',
            company: null,
        );
        $event->setCustomer($customer);

        $this->dispatch($event, TheliaEvents::ADDRESS_CREATE);

        $address = $event->getAddress();
        self::assertNotNull($address);
        self::assertSame($customer->getId(), $address->getCustomerId());
        self::assertSame('Home', $address->getLabel());
        self::assertSame('75002', $address->getZipcode());
    }

    public function testUpdateChangesAddressFields(): void
    {
        $title = $this->factory->customerTitle();
        $customer = $this->factory->customer($title);
        $country = $this->factory->country();
        $address = $this->factory->address($customer, $country, $title, [
            'address1' => 'Old street',
            'city' => 'Paris',
        ]);

        $event = new AddressCreateOrUpdateEvent(
            label: 'Updated',
            title: $title->getId(),
            firstname: 'Jane',
            lastname: 'Doe',
            address1: 'New street',
            address2: '',
            address3: '',
            zipcode: '69000',
            city: 'Lyon',
            country: $country->getId(),
            cellphone: '',
            phone: '',
            company: null,
        );
        $event->setAddress($address);

        $this->dispatch($event, TheliaEvents::ADDRESS_UPDATE);

        $reloaded = AddressQuery::create()->findPk($address->getId());
        self::assertSame('New street', $reloaded->getAddress1());
        self::assertSame('Lyon', $reloaded->getCity());
        self::assertSame('Jane', $reloaded->getFirstname());
    }

    public function testUseDefaultFlagsAddressAsDefault(): void
    {
        $title = $this->factory->customerTitle();
        $customer = $this->factory->customer($title);
        $country = $this->factory->country();
        $first = $this->factory->address($customer, $country, $title);
        $second = $this->factory->address($customer, $country, $title);

        $this->dispatch(new AddressEvent($second), TheliaEvents::ADDRESS_DEFAULT);

        self::assertSame(1, (int) AddressQuery::create()->findPk($second->getId())->getIsDefault());
        self::assertSame(0, (int) AddressQuery::create()->findPk($first->getId())->getIsDefault());
    }
}
