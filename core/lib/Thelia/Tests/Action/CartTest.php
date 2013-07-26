<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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

use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Cart;
use Thelia\Model\Customer;

class CartTest extends \PHPUnit_Framework_TestCase
{

    public $session;

    public $request;

    public $actionCart;

    public $uniqid;



    public function setUp()
    {
        $this->session = new Session(new MockArraySessionStorage());
        $this->request = new Request();

        $this->request->setSession($this->session);

        $this->uniqid = uniqid('', true);

        $this->actionCart = $this->getMock(
            "\Thelia\Action\Cart",
            array("generateCookie")
        );

        $this->actionCart
            ->expects($this->any())
            ->method("generateCookie")
            ->will($this->returnValue($this->uniqid));
    }

    public function testGetCartWithoutCustomerAndWithoutExistingCart()
    {
        $actionCart = $this->actionCart;

        $cart = $actionCart->getCart($this->request);

        $this->assertInstanceOf("Thelia\Model\Cart", $cart, '$cart must be an instance of cart model Thelia\Model\Cart');
        $this->assertNull($cart->getCustomerId());
        $this->assertNull($cart->getAddressDeliveryId());
        $this->assertNull($cart->getAddressInvoiceId());
        $this->assertEquals($this->uniqid, $cart->getToken());

    }

    public function testGetCartWithCustomerAndWithoutExistingCart()
    {
        $actionCart = $this->actionCart;

        $request = $this->request;

        //create a fake customer just for test. If not persists test fails !
        $customer = new Customer();
        $customer->setFirstname("john");
        $customer->setLastname("doe");
        $customer->setTitleId(1);
        $customer->save();

        $request->getSession()->setCustomerUser($customer);

        $cart = $actionCart->getCart($request);
        $this->assertInstanceOf("Thelia\Model\Cart", $cart, '$cart must be an instance of cart model Thelia\Model\Cart');
        $this->assertNotNull($cart->getCustomerId());
        $this->assertEquals($customer->getId(), $cart->getCustomerId());
        $this->assertNull($cart->getAddressDeliveryId());
        $this->assertNull($cart->getAddressInvoiceId());
        $this->assertEquals($this->uniqid, $cart->getToken());

    }

    public function testGetCartWithoutCustomerAndWithExistingCart()
    {
        $actionCart = $this->actionCart;

        $request = $this->request;
        $uniqid = uniqid("test1", true);
        //create a fake cart in database;
        $cart = new Cart();
        $cart->setToken($uniqid);
        $cart->save();

        $request->cookies->set("thelia_cart", $uniqid);

        $getCart = $actionCart->getCart($request);
        $this->assertInstanceOf("Thelia\Model\Cart", $getCart, '$cart must be an instance of cart model Thelia\Model\Cart');
        $this->assertNull($getCart->getCustomerId());
        $this->assertNull($getCart->getAddressDeliveryId());
        $this->assertNull($getCart->getAddressInvoiceId());
        $this->assertEquals($cart->getToken(), $getCart->getToken());
    }

    public function testGetCartWithExistingCartButNotGoodCookies()
    {
        $actionCart = $this->actionCart;

        $request = $this->request;

        $token = "WrongToken";
        $request->cookies->set("thelia_cart", $token);

        $cart = $actionCart->getCart($request);
        $this->assertInstanceOf("Thelia\Model\Cart", $cart, '$cart must be an instance of cart model Thelia\Model\Cart');
        $this->assertNull($cart->getCustomerId());
        $this->assertNull($cart->getAddressDeliveryId());
        $this->assertNull($cart->getAddressInvoiceId());
        $this->assertNotEquals($token, $cart->getToken());
    }

    public function testGetCartWithExistingCartAndCustomer()
    {
        $actionCart = $this->actionCart;

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

        $getCart = $actionCart->getCart($request);
        $this->assertInstanceOf("Thelia\Model\Cart", $getCart, '$cart must be an instance of cart model Thelia\Model\Cart');
        $this->assertNotNull($getCart->getCustomerId());
        $this->assertNull($getCart->getAddressDeliveryId());
        $this->assertNull($getCart->getAddressInvoiceId());
        $this->assertEquals($cart->getToken(), $getCart->getToken(), "token must be the same");
        $this->assertEquals($customer->getId(), $getCart->getCustomerId());
    }

    public function testGetCartWithExistinsCartAndCustomerButNotSameCustomerId()
    {
        $actionCart = $this->actionCart;

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

        $getCart = $actionCart->getCart($request);
        $this->assertInstanceOf("Thelia\Model\Cart", $getCart, '$cart must be an instance of cart model Thelia\Model\Cart');
        $this->assertNotNull($getCart->getCustomerId());
        $this->assertNull($getCart->getAddressDeliveryId());
        $this->assertNull($getCart->getAddressInvoiceId());
        $this->assertNotEquals($cart->getToken(), $getCart->getToken(), "token must be different");
        $this->assertEquals($customer->getId(), $getCart->getCustomerId());
    }

}