<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Tests\Action;

use Propel\Runtime\Propel;
use Thelia\Action\Address;
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Model\AddressQuery;
use Thelia\Model\CustomerQuery;

/**
 *
 * test address eventListener
 *
 * Class AddressTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AddressTest extends BaseAction
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

        $actionAddress = new Address();
        $actionAddress->create($AddressCreateOrUpdateEvent, null, $this->getMockEventDispatcher());

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

        $actionAddress = new Address();
        $actionAddress->update($addressEvent, null, $this->getMockEventDispatcher());

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

    /**
     * Bug found in Thelia 2.0.2
     */
    public function testUpdateDefaultAddress()
    {
        /**
         * Disable propel cache in order to get a new instance of the
         * active record in $updatedAddress
         */
        Propel::disableInstancePooling();

        /**
         * Get a customer and it's default address
         */
        $customer = CustomerQuery::create()->findOne();
        $defaultAddress = $customer->getDefaultAddress();
        $addressId = $defaultAddress->getId();

        /**
         * Try to update the address, and set the isDefault argument,
         * that should keep this address as the default one.
         */
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
            "",
            1
        );

        $addressEvent->setAddress($defaultAddress);

        /**
         * Do the update
         */
        $actionAddress = new Address();
        $actionAddress->update($addressEvent, null, $this->getMockEventDispatcher());

        $updatedAddress = AddressQuery::create()
            ->findPk($addressId);

        /**
         * This address should still be the default address
         */
        $this->assertEquals(
            1,
            $updatedAddress->getIsDefault()
        );

        /**
         * Renable it after
         */
        Propel::enableInstancePooling();
    }
}
