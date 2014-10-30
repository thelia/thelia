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

use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Tools\TokenProvider;

/**
 * Test the helpers adding in Session class
 *
 * Class SessionTest
 * @package Thelia\Tests\Core\HttpFoundation\Session
 * @author Manuel Raynaud <manu@thelia.net>
 */
class SessionTest extends \PHPUnit_Framework_TestCase
{
    /** @var  Session */
    protected $session;

    /** @var  Request */
    protected $request;

    protected $dispatcher, $dispatcherNull;

    protected $cartToken;

    protected $cartAction;

    public function setUp()
    {
        $this->request = new Request();

        $this->session = new Session(new MockArraySessionStorage());

        $this->request->setSession($this->session);

        $translator = new Translator($this->getMock("\Symfony\Component\DependencyInjection\ContainerInterface"));

        $this->cartAction = new \Thelia\Action\Cart(
            $this->request,
            new TokenProvider($this->request, $translator, 'baba au rhum')
        );

        $this->dispatcherNull = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");

        $this->dispatcher = $this->getMock(
            "Symfony\Component\EventDispatcher\EventDispatcherInterface",
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
            ->will($this->returnCallback(function ($type, $event) {
                $event->setDispatcher($this->dispatcher);

                if ($type == TheliaEvents::CART_RESTORE_CURRENT) {
                    $this->cartAction->restoreCurrentCart($event);
                }
                elseif ($type == TheliaEvents::CART_CREATE_NEW) {
                    $this->cartAction->createEmptyCart($event);
                }
            }
        ));
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

        $cart = @$session->getCart();
    }

    /**
     * @expectedException \LogicException
     */
    public function testGetCartUnableToCreateCart()
    {
        $session = $this->session;

        $cart = $session->getSessionCart($this->dispatcherNull);
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

        $this->request->cookies->set(ConfigQuery::read("cart.cookie_name", 'thelia_cart'), $testCart->getToken());

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

        $this->request->cookies->set(ConfigQuery::read("cart.cookie_name", 'thelia_cart'), $testCart->getToken());

        $cart = $session->getSessionCart($this->dispatcher);

        $this->assertNotNull($cart);
        $this->assertInstanceOf("\Thelia\Model\Cart", $cart, '$cart must be an instance of Thelia\Model\Cart');
    }
}
