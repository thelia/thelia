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

namespace Thelia\Tests\Unit\Domain\Addressing;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Addressing\AddressFacade;
use Thelia\Domain\Addressing\DTO\AddressDTO;
use Thelia\Domain\Customer\Exception\CustomerException;
use Thelia\Model\Address;
use Thelia\Model\Customer;

class AddressFacadeTest extends TestCase
{
    private MockObject&EventDispatcherInterface $dispatcher;
    private MockObject&Session $session;
    private AddressFacade $facade;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->session = $this->createMock(Session::class);
        $this->facade = new AddressFacade($this->dispatcher, $this->session);
    }

    public function testCreateWithCustomerInSession(): void
    {
        $customer = $this->createCustomerMock(1);
        $dto = $this->createAddressDTO();
        $address = $this->createAddressMock(10, 1, $customer);

        $this->session
            ->expects($this->once())
            ->method('getCustomerUser')
            ->willReturn($customer);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (AddressCreateOrUpdateEvent $event) use ($customer, $address) {
                    $this->assertSame('Test Address', $event->getLabel());
                    $this->assertSame('John', $event->getFirstname());
                    $this->assertSame('Doe', $event->getLastname());
                    $this->assertSame('123 Test Street', $event->getAddress1());
                    $this->assertSame('75001', $event->getZipcode());
                    $this->assertSame('Paris', $event->getCity());
                    $this->assertSame(64, $event->getCountry());
                    $this->assertSame($customer, $event->getCustomer());

                    $event->setAddress($address);

                    return true;
                }),
                TheliaEvents::ADDRESS_CREATE
            );

        $result = $this->facade->create($dto);

        $this->assertSame($address, $result);
    }

    public function testCreateWithExplicitCustomer(): void
    {
        $customer = $this->createCustomerMock(1);
        $dto = $this->createAddressDTO();
        $address = $this->createAddressMock(10, 1, $customer);

        $this->session
            ->expects($this->never())
            ->method('getCustomerUser');

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (AddressCreateOrUpdateEvent $event) use ($customer, $address) {
                    $this->assertSame($customer, $event->getCustomer());
                    $event->setAddress($address);

                    return true;
                }),
                TheliaEvents::ADDRESS_CREATE
            );

        $result = $this->facade->create($dto, $customer);

        $this->assertSame($address, $result);
    }

    public function testCreateWithoutCustomerThrowsException(): void
    {
        $dto = $this->createAddressDTO();

        $this->session
            ->expects($this->once())
            ->method('getCustomerUser')
            ->willReturn(null);

        $this->dispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->expectException(CustomerException::class);
        $this->facade->create($dto);
    }

    public function testCreateEventContainsAllDTOFields(): void
    {
        $customer = $this->createCustomerMock(1);
        $address = $this->createAddressMock(10, 1, $customer);

        $dto = new AddressDTO(
            label: 'Home Address',
            firstname: 'Jane',
            lastname: 'Smith',
            address1: '456 Main Street',
            zipcode: '69001',
            city: 'Lyon',
            countryId: 64,
            titleId: 2,
            address2: 'Building B',
            address3: 'Floor 3',
            phone: '0123456789',
            cellphone: '0612345678',
            company: 'ACME Corp',
            stateId: 10,
            isDefault: true,
        );

        $this->session->method('getCustomerUser')->willReturn($customer);

        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (AddressCreateOrUpdateEvent $event) use ($address) {
                    $this->assertSame('Home Address', $event->getLabel());
                    $this->assertSame(2, $event->getTitle());
                    $this->assertSame('Jane', $event->getFirstname());
                    $this->assertSame('Smith', $event->getLastname());
                    $this->assertSame('456 Main Street', $event->getAddress1());
                    $this->assertSame('Building B', $event->getAddress2());
                    $this->assertSame('Floor 3', $event->getAddress3());
                    $this->assertSame('69001', $event->getZipcode());
                    $this->assertSame('Lyon', $event->getCity());
                    $this->assertSame(64, $event->getCountry());
                    $this->assertSame(10, $event->getState());
                    $this->assertSame('0123456789', $event->getPhone());
                    $this->assertSame('0612345678', $event->getCellphone());
                    $this->assertSame('ACME Corp', $event->getCompany());
                    $this->assertSame(1, $event->getIsDefault());

                    $event->setAddress($address);

                    return true;
                }),
                TheliaEvents::ADDRESS_CREATE
            );

        $this->facade->create($dto);
    }

    public function testGetByCustomerReturnsEmptyArrayWithoutCustomer(): void
    {
        $this->session
            ->expects($this->once())
            ->method('getCustomerUser')
            ->willReturn(null);

        $result = $this->facade->getByCustomer();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetDefaultReturnsNullWithoutCustomer(): void
    {
        $this->session
            ->expects($this->once())
            ->method('getCustomerUser')
            ->willReturn(null);

        $result = $this->facade->getDefault();

        $this->assertNull($result);
    }

    public function testGetDefaultWithExplicitCustomer(): void
    {
        $customer = $this->createCustomerMock(1);
        $defaultAddress = $this->createAddressMock(5);

        $customer
            ->expects($this->once())
            ->method('getDefaultAddress')
            ->willReturn($defaultAddress);

        $this->session
            ->expects($this->never())
            ->method('getCustomerUser');

        $result = $this->facade->getDefault($customer);

        $this->assertSame($defaultAddress, $result);
    }

    public function testAddressDTOToArray(): void
    {
        $dto = new AddressDTO(
            label: 'Home',
            firstname: 'John',
            lastname: 'Doe',
            address1: '123 Main St',
            zipcode: '75001',
            city: 'Paris',
            countryId: 64,
            titleId: 1,
            address2: 'Apt 4',
            phone: '0123456789',
            isDefault: true,
        );

        $array = $dto->toArray();

        $this->assertSame('Home', $array['label']);
        $this->assertSame(1, $array['title']);
        $this->assertSame('John', $array['firstname']);
        $this->assertSame('Doe', $array['lastname']);
        $this->assertSame('123 Main St', $array['address1']);
        $this->assertSame('Apt 4', $array['address2']);
        $this->assertSame('75001', $array['zipcode']);
        $this->assertSame('Paris', $array['city']);
        $this->assertSame(64, $array['country']);
        $this->assertSame('0123456789', $array['phone']);
        $this->assertTrue($array['is_default']);
    }

    public function testAddressDTODefaultValues(): void
    {
        $dto = new AddressDTO(
            label: 'Test',
            firstname: 'John',
            lastname: 'Doe',
            address1: '123 Street',
            zipcode: '75001',
            city: 'Paris',
            countryId: 64,
        );

        $this->assertNull($dto->titleId);
        $this->assertNull($dto->address2);
        $this->assertNull($dto->address3);
        $this->assertNull($dto->phone);
        $this->assertNull($dto->cellphone);
        $this->assertNull($dto->company);
        $this->assertNull($dto->stateId);
        $this->assertFalse($dto->isDefault);
    }

    private function createAddressDTO(): AddressDTO
    {
        return new AddressDTO(
            label: 'Test Address',
            firstname: 'John',
            lastname: 'Doe',
            address1: '123 Test Street',
            zipcode: '75001',
            city: 'Paris',
            countryId: 64,
            titleId: 1,
        );
    }

    private function createCustomerMock(int $id): MockObject&Customer
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn($id);

        return $customer;
    }

    private function createAddressMock(int $id, ?int $customerId = null, ?Customer $customer = null): MockObject&Address
    {
        $address = $this->createMock(Address::class);
        $address->method('getId')->willReturn($id);
        $address->method('getCustomerId')->willReturn($customerId);

        if ($customer !== null) {
            $address->method('getCustomer')->willReturn($customer);
        }

        return $address;
    }
}
