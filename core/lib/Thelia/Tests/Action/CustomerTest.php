<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Tests\Action\ImageTest;
use Thelia\Action\Customer;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;

/**
 * Class CustomerTest
 * @package Thelia\Tests\Action\ImageTest
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CustomerTest extends \PHPUnit_Framework_TestCase
{
    public function getContainer()
    {
        $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

        $dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");

        $container->set("event_dispatcher", $dispatcher);

        return $container;
    }

    public function testCreatedCustomer()
    {
        $customerCreateEvent = new CustomerCreateOrUpdateEvent(
            1,
            "thelia",
            "thelia",
            "street address 1",
            "street address 2",
            "street address 3",
            "0102030405",
            "0607080910",
            "63000",
            "clermont-ferrand",
            64,
            sprintf("%s@thelia.fr", uniqid()),
            uniqid(),
            1,
            0,
            0,
            0,
            'My super company',
            null
        );

        $customerAction = new Customer($this->getContainer());

        $customerAction->create($customerCreateEvent);

        $customerCreated = $customerCreateEvent->getCustomer();

        $this->assertInstanceOf("Thelia\Model\Customer", $customerCreated, "new customer created must be an instance of Thelia\Model\Customer");
        $this->assertFalse($customerCreated->isNew());

        $this->assertEquals($customerCreateEvent->getFirstname(), $customerCreated->getFirstname());
        $this->assertEquals($customerCreateEvent->getLastname(), $customerCreated->getLastname());
        $this->assertEquals($customerCreateEvent->getTitle(), $customerCreated->getTitleId());
        $this->assertEquals($customerCreateEvent->getEmail(), $customerCreated->getEmail());
        $this->assertEquals($customerCreateEvent->getReseller(), $customerCreated->getReseller());
        $this->assertEquals($customerCreateEvent->getSponsor(), $customerCreated->getSponsor());
        $this->assertEquals($customerCreateEvent->getDiscount(), $customerCreated->getDiscount());

        $addressCreated = $customerCreated->getDefaultAddress();

        $this->assertInstanceOf("Thelia\Model\Address", $addressCreated);

        $this->assertEquals($customerCreateEvent->getFirstname(), $addressCreated->getFirstname());
        $this->assertEquals($customerCreateEvent->getLastname(), $addressCreated->getLastname());
        $this->assertEquals($customerCreateEvent->getTitle(), $addressCreated->getTitleId());
        $this->assertEquals($customerCreateEvent->getAddress1(), $addressCreated->getAddress1());
        $this->assertEquals($customerCreateEvent->getAddress2(), $addressCreated->getAddress2());
        $this->assertEquals($customerCreateEvent->getAddress3(), $addressCreated->getAddress3());
        $this->assertEquals($customerCreateEvent->getZipcode(), $addressCreated->getZipcode());
        $this->assertEquals($customerCreateEvent->getCity(), $addressCreated->getCity());
        $this->assertEquals($customerCreateEvent->getCountry(), $addressCreated->getCountryId());
        $this->assertEquals($customerCreateEvent->getPhone(), $addressCreated->getPhone());
        $this->assertEquals($customerCreateEvent->getCellphone(), $addressCreated->getCellphone());
        $this->assertEquals($customerCreateEvent->getCompany(), $addressCreated->getCompany());
    }

    public function testCreatedCustomerWithSpecifiedRef()
    {
        $customerCreateEvent = new CustomerCreateOrUpdateEvent(
            1,
            "thelia",
            "thelia",
            "street address 1",
            "street address 2",
            "street address 3",
            "0102030405",
            "0607080910",
            "63000",
            "clermont-ferrand",
            64,
            sprintf("%s@thelia.fr", uniqid()),
            uniqid(),
            1,
            0,
            0,
            0,
            'My super company',
            'testRef'
        );

        $customerAction = new Customer($this->getContainer());

        $customerAction->create($customerCreateEvent);

        $customerCreated = $customerCreateEvent->getCustomer();

        $this->assertInstanceOf("Thelia\Model\Customer", $customerCreated, "new customer created must be an instance of Thelia\Model\Customer");
        $this->assertFalse($customerCreated->isNew());

        $this->assertEquals($customerCreateEvent->getFirstname(), $customerCreated->getFirstname());
        $this->assertEquals($customerCreateEvent->getLastname(), $customerCreated->getLastname());
        $this->assertEquals($customerCreateEvent->getTitle(), $customerCreated->getTitleId());
        $this->assertEquals($customerCreateEvent->getEmail(), $customerCreated->getEmail());
        $this->assertEquals($customerCreateEvent->getReseller(), $customerCreated->getReseller());
        $this->assertEquals($customerCreateEvent->getSponsor(), $customerCreated->getSponsor());
        $this->assertEquals($customerCreateEvent->getDiscount(), $customerCreated->getDiscount());
        $this->assertEquals($customerCreateEvent->getRef(), $customerCreated->getRef());

        $addressCreated = $customerCreated->getDefaultAddress();

        $this->assertInstanceOf("Thelia\Model\Address", $addressCreated);

        $this->assertEquals($customerCreateEvent->getFirstname(), $addressCreated->getFirstname());
        $this->assertEquals($customerCreateEvent->getLastname(), $addressCreated->getLastname());
        $this->assertEquals($customerCreateEvent->getTitle(), $addressCreated->getTitleId());
        $this->assertEquals($customerCreateEvent->getAddress1(), $addressCreated->getAddress1());
        $this->assertEquals($customerCreateEvent->getAddress2(), $addressCreated->getAddress2());
        $this->assertEquals($customerCreateEvent->getAddress3(), $addressCreated->getAddress3());
        $this->assertEquals($customerCreateEvent->getZipcode(), $addressCreated->getZipcode());
        $this->assertEquals($customerCreateEvent->getCity(), $addressCreated->getCity());
        $this->assertEquals($customerCreateEvent->getCountry(), $addressCreated->getCountryId());
        $this->assertEquals($customerCreateEvent->getPhone(), $addressCreated->getPhone());
        $this->assertEquals($customerCreateEvent->getCellphone(), $addressCreated->getCellphone());
        $this->assertEquals($customerCreateEvent->getCompany(), $addressCreated->getCompany());
    }
}
