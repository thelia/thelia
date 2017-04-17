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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Action\Customer;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\CustomerQuery;

/**
 * Class CustomerTest
 * @package Thelia\Tests\Action\ImageTest
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CustomerTest extends BaseAction
{
    /**
     * @var SecurityContext
     */
    protected $securityContext;

    protected $request;

    protected $customerAction;

    public static function setUpBeforeClass()
    {
        CustomerQuery::create()
            ->filterByRef('testRef')
            ->delete();
    }

    public function setUp()
    {
        $session = new Session(new MockArraySessionStorage());

        $this->request = new Request();
        $this->request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($this->request);

        $this->securityContext = new SecurityContext($requestStack);

        $this->customerAction = new Customer(
            $this->securityContext,
            new MailerFactory($this->getMockEventDispatcher(), $this->getMockParserInterface()),
            $this->getMockEventDispatcher()
        );
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

        /** @var Customer $customerAction */
        $customerAction = $this->customerAction;

        $customerAction->create($customerCreateEvent, null, $this->getMockEventDispatcher());

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

        /** @var Customer $customerAction */
        $customerAction = $this->customerAction;

        $customerAction->create($customerCreateEvent, null, $this->getMockEventDispatcher());

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
