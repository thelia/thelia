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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Action\Customer;
use Thelia\Core\Event\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\OrderEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Base\AddressQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order as OrderModel;
use Thelia\Action\Order;
use Thelia\Module\BaseModule;

/**
 * Class CustomerTest
 * @package Thelia\Tests\Action
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ContainerBuilder $container
     */
    protected $container;

    /**
     * @var Order $orderAction
     */
    protected $orderAction;

    /**
     * @var OrderEvent $orderEvent
     */
    protected $orderEvent;

    protected $customer;

    public function setUp()
    {
        $container = new ContainerBuilder();

//        $session = new Session(new MockArraySessionStorage());
//        $request = new Request();
//        $dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");

//        $request->setSession($session);

//        $container->set("event_dispatcher", $dispatcher);
//        $container->set('request', $request);
//        $container->set('thelia.securityContext', new SecurityContext($request));

        $this->container = $container;

        $this->orderEvent = new OrderEvent(new OrderModel());

        $this->orderAction = new Order($this->container);

        /* load customer */
        /*$this->customer = $this->loadCustomer();
        if(null === $this->customer) {
            return;
        }*/
    }

    public function loadCustomer()
    {
        $customer = CustomerQuery::create()->findOne();
        if(null === $customer) {
            return null;
        }

        $this->container->get('thelia.securityContext')->setCustomerUser($customer);

        return $customer;
    }

    public function testSetDeliveryAddress()
    {
        //$validAddressId = AddressQuery::create()->findOneByCustomerId($this->customer->getId());

        $this->orderEvent->setDeliveryAddress(321);

        $this->orderAction->setDeliveryAddress($this->orderEvent);

        $this->assertEquals(
            321,
            $this->orderEvent->getOrder()->chosenDeliveryAddress
        );
    }

    public function testSetinvoiceAddress()
    {
        $this->orderEvent->setInvoiceAddress(654);

        $this->orderAction->setInvoiceAddress($this->orderEvent);

        $this->assertEquals(
            654,
            $this->orderEvent->getOrder()->chosenInvoiceAddress
        );
    }

    public function testSetDeliveryModule()
    {
        /*$deliveryModule = ModuleQuery::create()
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE)
            ->filterByActivate(1)
            ->findOne();

        if(null === $deliveryModule) {
            return;
        }*/

        $this->orderEvent->setDeliveryModule(123);

        $this->orderAction->setDeliveryModule($this->orderEvent);

        $this->assertEquals(
            123,
            $this->orderEvent->getOrder()->getDeliveryModuleId()
        );
    }

    public function testSetPaymentModule()
    {
        $this->orderEvent->setPaymentModule(456);

        $this->orderAction->setPaymentModule($this->orderEvent);

        $this->assertEquals(
            456,
            $this->orderEvent->getOrder()->getPaymentModuleId()
        );
    }
}