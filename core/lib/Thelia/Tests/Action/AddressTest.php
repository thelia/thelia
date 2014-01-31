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

namespace Thelia\Tests\Action;

use Thelia\Action\Address;
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Model\Base\CustomerQuery;
use Thelia\Tests\Action\BaseAction;

/**
 *
 * test address eventListener
 *
 * Class AddressTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AddressTest extends \PHPUnit_Framework_TestCase
{

    public function testCreatedAddress()
    {
        $customer = CustomerQuery::create()->findOne();

        $AddressCreateOrUpdateEvent = new AddressCreateOrUpdateEvent(
            "test address",
            1,
            "Thelia",
            "Thelia",
            "5 rue rochon",
            "",
            "",
            "63000",
            "clermont-ferrand",
            64,
            "0102030405",
            "",
            ""
        );
        $AddressCreateOrUpdateEvent->setCustomer($customer);
        $AddressCreateOrUpdateEvent->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"));

        $actionAddress = new Address();
        $actionAddress->create($AddressCreateOrUpdateEvent);

        $createdAddress = $AddressCreateOrUpdateEvent->getAddress();

        $this->assertInstanceOf("Thelia\Model\Address", $createdAddress);
        $this->assertFalse($createdAddress->isNew());
        $this->assertSame($customer, $createdAddress->getCustomer());

        $this->assertEquals($AddressCreateOrUpdateEvent->getLabel(), $createdAddress->getLabel());
        $this->assertEquals($AddressCreateOrUpdateEvent->getTitle(), $createdAddress->getTitleId());
        $this->assertEquals($AddressCreateOrUpdateEvent->getFirstname(), $createdAddress->getFirstname());
        $this->assertEquals($AddressCreateOrUpdateEvent->getLastname(), $createdAddress->getLastname());
        $this->assertEquals($AddressCreateOrUpdateEvent->getAddress1(), $createdAddress->getAddress1());
        $this->assertEquals($AddressCreateOrUpdateEvent->getAddress2(), $createdAddress->getAddress2());
        $this->assertEquals($AddressCreateOrUpdateEvent->getAddress3(), $createdAddress->getAddress3());
        $this->assertEquals($AddressCreateOrUpdateEvent->getZipcode(), $createdAddress->getZipcode());
        $this->assertEquals($AddressCreateOrUpdateEvent->getCity(), $createdAddress->getCity());
        $this->assertEquals($AddressCreateOrUpdateEvent->getCountry(), $createdAddress->getCountryId());
        $this->assertEquals($AddressCreateOrUpdateEvent->getPhone(), $createdAddress->getPhone());
        $this->assertEquals($AddressCreateOrUpdateEvent->getCellphone(), $createdAddress->getCellphone());
        $this->assertEquals($AddressCreateOrUpdateEvent->getCompany(), $createdAddress->getCompany());
    }

    public function testUpdatedAddress()
    {

        $customer = CustomerQuery::create()->findOne();
        $address = $customer->getAddresses()->getFirst();

        $addressEvent = new AddressCreateOrUpdateEvent(
            "",
            1,
            "Thelia modif",
            "Thelia modif",
            "cour des étoiles",
            "rue des miracles",
            "",
            "63000",
            "clermont-ferrand",
            64,
            "0102030405",
            "",
            ""
        );
        $addressEvent->setAddress($address);
        $addressEvent->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"));

        $actionAddress = new Address();
        $actionAddress->update($addressEvent);

        $updatedAddress = $addressEvent->getAddress();
        $this->assertInstanceOf("Thelia\Model\Address", $updatedAddress);
        $this->assertFalse($updatedAddress->isNew());
        $this->assertSame($customer, $updatedAddress->getCustomer());

        $this->assertEquals($address->getLabel(), $updatedAddress->getLabel());
        $this->assertEquals($addressEvent->getTitle(), $updatedAddress->getTitleId());
        $this->assertEquals($addressEvent->getFirstname(), $updatedAddress->getFirstname());
        $this->assertEquals($addressEvent->getLastname(), $updatedAddress->getLastname());
        $this->assertEquals($addressEvent->getAddress1(), $updatedAddress->getAddress1());
        $this->assertEquals($addressEvent->getAddress2(), $updatedAddress->getAddress2());
        $this->assertEquals($addressEvent->getAddress3(), $updatedAddress->getAddress3());
        $this->assertEquals($addressEvent->getZipcode(), $updatedAddress->getZipcode());
        $this->assertEquals($addressEvent->getCity(), $updatedAddress->getCity());
        $this->assertEquals($addressEvent->getCountry(), $updatedAddress->getCountryId());
        $this->assertEquals($addressEvent->getPhone(), $updatedAddress->getPhone());
        $this->assertEquals($addressEvent->getCellphone(), $updatedAddress->getCellphone());
        $this->assertEquals($addressEvent->getCompany(), $updatedAddress->getCompany());

    }

}
