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

namespace Thelia\Tests\Core\HttpFoundation\Session;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Tools\TokenProvider;

/**
 * Test the helpers adding in Session class
 *
 * Class SessionTest
 * @package Thelia\Tests\Core\HttpFoundation\Session
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class SessionTest extends \PHPUnit_Framework_TestCase
{
    /** @var  Session */
    protected $session;

    /** @var RequestStack */
    protected $requestStack;

    /** @var EventDispatcher */
    protected $dispatcher;

    protected $dispatcherNull;

    protected $cartToken;

    protected $cartAction;

    public function setUp()
    {
        $this->requestStack = new RequestStack();

        $request = new Request();

        $this->requestStack->push($request);

        $this->session = new Session(new MockArraySessionStorage());

        $request->setSession($this->session);

        $this->dispatcher = new EventDispatcher();

        $translator = new Translator($this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface'));

        $token = new TokenProvider($this->requestStack, $translator, 'test');

        $this->dispatcher->addSubscriber(new \Thelia\Action\Cart($this->requestStack, $token));

        $this->session->setSessionCart(null);

        $request->setSession($this->session);

        /** @var \Thelia\Action\Cart  cartAction */
        $this->cartAction = new \Thelia\Action\Cart(
            $this->requestStack,
            new TokenProvider($this->requestStack, $translator, 'baba au rhum')
        );

        $this->dispatcherNull = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $this->dispatcher = $this->getMock(
            'Symfony\Component\EventDispatcher\EventDispatcherInterface',
            array(),
            array(),
            '',
            true,
            true,
            true,
            false
        );

        $this->dispatcher
            ->expects($this->any())
            ->method('dispatch')
            ->will(
                $this->returnCallback(
                    function ($type, $event) {
                        if ($type == TheliaEvents::CART_RESTORE_CURRENT) {
                            $this->cartAction->restoreCurrentCart($event, null, $this->dispatcher);
                        } elseif ($type == TheliaEvents::CART_CREATE_NEW) {
                            $this->cartAction->createEmptyCart($event, null, $this->dispatcher);
                        }
                    }
                )
            );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetCartWithoutExistingCartAndNoDispatcher()
    {
        $session = $this->session;

        $cart = $session->getSessionCart();
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetCartWithoutExistingCartAndDeprecatedMethod()
    {
        $session = $this->session;

        @$session->getSessionCart();
    }

    /**
     * @expectedException \LogicException
     */
    public function testGetCartUnableToCreateCart()
    {
        $session = $this->session;

        $session->getSessionCart($this->dispatcherNull);
    }

    public function testGetCartWithoutExistingCartNoCustomer()
    {
        $session = $this->session;

        $cart = $session->getSessionCart($this->dispatcher);

        $this->assertNotNull($cart);
        $this->assertInstanceOf("\Thelia\Model\Cart", $cart, '$cart must be an instance of Thelia\Model\Cart');
    }

    public function testGetCartWithExistingCustomerButNoCart()
    {
        $session = $this->session;

        //create a fake customer just for test. If not persists test fails !
        $customer = new Customer();
        $customer->setFirstname("john test session");
        $customer->setLastname("doe");
        $customer->setTitleId(1);
        $customer->save();

        $session->setCustomerUser($customer);

        $cart = $session->getSessionCart($this->dispatcher);

        $this->assertNotNull($cart);
        $this->assertEquals($customer->getId(), $cart->getCustomerId());
        $this->assertInstanceOf("\Thelia\Model\Cart", $cart, '$cart must be an instance of Thelia\Model\Cart');
    }

    public function testGetCartWithExistingCartAndCustomerButWithoutReferenceToCustomerInCart()
    {
        $session = $this->session;

        // create a fake customer just for test. If not persists test fails !
        $customer = new Customer();
        $customer->setFirstname("john test session");
        $customer->setLastname("doe");
        $customer->setTitleId(1);
        $customer->save();

        $session->setCustomerUser($customer);

        $testCart = new Cart();
        $testCart->setToken(uniqid("testSessionGetCart2", true));
        $testCart->save();

        $this->requestStack->getCurrentRequest()->cookies->set(ConfigQuery::read("cart.cookie_name", 'thelia_cart'), $testCart->getToken());

        $cart = $session->getSessionCart($this->dispatcher);

        $this->assertNotNull($cart);
        $this->assertEquals($customer->getId(), $cart->getCustomerId());
        $this->assertInstanceOf("\Thelia\Model\Cart", $cart, '$cart must be an instance of Thelia\Model\Cart');
    }

    public function testGetCartWithExistingCartAndCustomerAndReferencesEachOther()
    {
        $session = $this->session;

        //create a fake customer just for test. If not persists test fails !
        $customer = new Customer();
        $customer->setFirstname("john test session");
        $customer->setLastname("doe");
        $customer->setTitleId(1);
        $customer->save();

        $session->setCustomerUser($customer);

        $testCart = new Cart();
        $testCart->setToken(uniqid("testSessionGetCart3", true));
        $testCart->setCustomerId($customer->getId());
        $testCart->save();

        $this->requestStack->getCurrentRequest()->cookies->set(ConfigQuery::read("cart.cookie_name", 'thelia_cart'), $testCart->getToken());

        $cart = $session->getSessionCart($this->dispatcher);

        $this->assertNotNull($cart);
        $this->assertInstanceOf("\Thelia\Model\Cart", $cart, '$cart must be an instance of Thelia\Model\Cart');
    }

    public function testSetCurrency()
    {
        $session = new Session(new MockArraySessionStorage());

        $currentCurrency = (new Currency())->setId(99);
        $session->setCurrency($currentCurrency);
        $this->assertEquals($currentCurrency->getId(), $session->getCurrency()->getId());
    }

    public function testGetCurrencyWithParameterForceDefault()
    {
        $session = new Session(new MockArraySessionStorage());
        $this->assertNull($session->getCurrency(false));
    }

    public function testGetCurrency()
    {
        $session = new Session(new MockArraySessionStorage());
        $this->assertInstanceOf('Thelia\Model\Currency', $session->getCurrency());
    }

    public function testGetAdminEditionCurrencyWithCurrencyInSession()
    {
        $session = new Session(new MockArraySessionStorage());
        $currentCurrency = (new Currency())->setId(99);
        $session->setAdminEditionCurrency($currentCurrency);
        $this->assertEquals($currentCurrency->getId(), $session->getAdminEditionCurrency()->getId());
    }

    public function testGetAdminEditionCurrencyWithNoCurrencyInSession()
    {
        $session = new Session(new MockArraySessionStorage());
        $this->assertInstanceOf('Thelia\Model\Currency', $session->getAdminEditionCurrency());
    }
}
