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
namespace Thelia\Tests\Cart\CartTraitTest;

use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Cart;
use Thelia\Model\Customer;

/**
 * phpunit 3.8 needed for mcking a Trait and there is conflict with php version.
 *
 *
 * Class CartTraitTest
 * @package Thelia\Tests\Cart\CartTraitTest
 * @author Manuel Raynaud <manu@thelia.net>
 */
class CartTraitTest extends \PHPUnit_Framework_TestCase
{
    protected $session;

    protected $request;

    protected $cartTrait;

    protected $uniqid;

    protected $dispatcher;

    public function getContainer()
    {
        $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

        $dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");

        $container->set("event_dispatcher", $dispatcher);

        return $container;
    }

    public function setUp()
    {
        $this->session = new Session(new MockArraySessionStorage());
        $this->request = new Request();

        $this->request->setSession($this->session);

        $this->uniqid = uniqid('', true);

        $this->dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");

        $this->cartTrait = new MockCartTrait($this->uniqid, $this->getContainer());
    }

    /**
     * no cart present in session and cart_id no yet exists in cookies.
     *
     * In this case, a new cart instance must be create
     */
    public function testGetCartWithoutCustomerAndWithoutExistingCart()
    {
        $cartTrait = $this->cartTrait;

        $cart = $cartTrait->getCart($this->dispatcher, $this->request);

        $this->assertInstanceOf("Thelia\Model\Cart", $cart, '$cart must be an instance of cart model Thelia\Model\Cart');
        $this->assertNull($cart->getCustomerId());
        $this->assertNull($cart->getAddressDeliveryId());
        $this->assertNull($cart->getAddressInvoiceId());
        $this->assertEquals($this->uniqid, $cart->getToken());
    }

    /**
     * Customer is connected but his cart does not exists yet
     *
     * Cart must be created and associated to the current connected Customer
     */
    public function testGetCartWithCustomerAndWithoutExistingCart()
    {
        $cartTrait = $this->cartTrait;

        $request = $this->request;

        //create a fake customer just for test. If not persists test fails !
        $customer = new Customer();
        $customer->setFirstname("john");
        $customer->setLastname("doe");
        $customer->setTitleId(1);
        $customer->save();

        $request->getSession()->setCustomerUser($customer);

        $cart = $cartTrait->getCart($this->dispatcher, $request);
        $this->assertInstanceOf("Thelia\Model\Cart", $cart, '$cart must be an instance of cart model Thelia\Model\Cart');
        $this->assertNotNull($cart->getCustomerId());
        $this->assertEquals($customer->getId(), $cart->getCustomerId());
        $this->assertNull($cart->getAddressDeliveryId());
        $this->assertNull($cart->getAddressInvoiceId());
        $this->assertEquals($this->uniqid, $cart->getToken());
    }

    /**
     * Cart exists and his id put in cookies.
     *
     * Must return the same cart instance
     */
    public function testGetCartWithoutCustomerAndWithExistingCart()
    {
        $cartTrait = $this->cartTrait;

        $request = $this->request;
        $uniqid = uniqid("test1", true);
        //create a fake cart in database;
        $cart = new Cart();
        $cart->setToken($uniqid);
        $cart->save();

        $request->cookies->set("thelia_cart", $uniqid);

        $getCart = $cartTrait->getCart($this->dispatcher, $request);
        $this->assertInstanceOf("Thelia\Model\Cart", $getCart, '$cart must be an instance of cart model Thelia\Model\Cart');
        $this->assertNull($getCart->getCustomerId());
        $this->assertNull($getCart->getAddressDeliveryId());
        $this->assertNull($getCart->getAddressInvoiceId());
        $this->assertEquals($cart->getToken(), $getCart->getToken());
    }

    /**
     * a cart id exists in cookies but this id does not exists yet in databases
     *
     * a new cart must be created (different token)
     */
    public function testGetCartWithExistingCartButNotGoodCookies()
    {
        $cartTrait = $this->cartTrait;

        $request = $this->request;

        $token = "WrongToken";
        $request->cookies->set("thelia_cart", $token);

        $cart = $cartTrait->getCart($this->dispatcher, $request);
        $this->assertInstanceOf("Thelia\Model\Cart", $cart, '$cart must be an instance of cart model Thelia\Model\Cart');
        $this->assertNull($cart->getCustomerId());
        $this->assertNull($cart->getAddressDeliveryId());
        $this->assertNull($cart->getAddressInvoiceId());
        $this->assertNotEquals($token, $cart->getToken());
    }

    /**
     * cart and customer already exists. Cart and customer are linked.
     *
     * cart in session must be return
     */
    public function testGetCartWithExistingCartAndCustomer()
    {
        $cartTrait = $this->cartTrait;

        $request = $this->request;

        //create a fake customer just for test. If not persists test fails !
        $customer = new Customer();
        $customer->setFirstname("john");
        $customer->setLastname("doe");
        $customer->setTitleId(1);
        $customer->save();

        $uniqid = uniqid("test2", true);
        //create a fake cart in database;
        $cart = new Cart();
        $cart->setToken($uniqid);
        $cart->setCustomer($customer);
        $cart->save();

        $request->cookies->set("thelia_cart", $uniqid);

        $request->getSession()->setCustomerUser($customer);

        $getCart = $cartTrait->getCart($this->dispatcher, $request);
        $this->assertInstanceOf("Thelia\Model\Cart", $getCart, '$cart must be an instance of cart model Thelia\Model\Cart');
        $this->assertNotNull($getCart->getCustomerId());
        $this->assertNull($getCart->getAddressDeliveryId());
        $this->assertNull($getCart->getAddressInvoiceId());
        $this->assertEquals($cart->getToken(), $getCart->getToken(), "token must be the same");
        $this->assertEquals($customer->getId(), $getCart->getCustomerId());
    }

    /**
     * Customer is connected but cart not associated to him
     *
     * A new cart must be created (duplicated) containing customer id
     */
    public function testGetCartWithExistingCartAndCustomerButNotSameCustomerId()
    {
        $cartTrait = $this->cartTrait;

        $request = $this->request;

        //create a fake customer just for test. If not persists test fails !
        $customer = new Customer();
        $customer->setFirstname("john");
        $customer->setLastname("doe");
        $customer->setTitleId(1);
        $customer->save();

        $uniqid = uniqid("test3", true);
        //create a fake cart in database;
        $cart = new Cart();
        $cart->setToken($uniqid);

        $cart->save();

        $request->cookies->set("thelia_cart", $uniqid);

        $request->getSession()->setCustomerUser($customer);

        $getCart = $cartTrait->getCart($this->dispatcher, $request);
        $this->assertInstanceOf("Thelia\Model\Cart", $getCart, '$cart must be an instance of cart model Thelia\Model\Cart');
        $this->assertNotNull($getCart->getCustomerId());
        $this->assertNull($getCart->getAddressDeliveryId());
        $this->assertNull($getCart->getAddressInvoiceId());
        $this->assertNotEquals($cart->getToken(), $getCart->getToken(), "token must be different");
        $this->assertEquals($customer->getId(), $getCart->getCustomerId());
    }
}

/**
 * Only way to mock a trait before phpunit 3.8
 *
 * Class MockCartTrait
 * @package Thelia\Tests\Cart\CartTraitTest
 */
class MockCartTrait
{
    use \Thelia\Cart\CartTrait;

    public $uniqid;
    public $container;

    public function __construct($uniqid, $container)
    {
        $this->uniqid = $uniqid;
        $this->container = $container;
    }

    public function generateCookie()
    {
        return $this->uniqid;
    }

    public function getDispatcher()
    {
        return $this->container->get("event_dispatcher");
    }
}
