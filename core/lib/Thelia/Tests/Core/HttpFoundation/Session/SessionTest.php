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

namespace Thelia\Tests\Core\HttpFoundation\Session;

use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Cart;
use Thelia\Model\Customer;

/**
 * Test the helpers adding in Session class
 *
 * Class SessionTest
 * @package Thelia\Tests\Core\HttpFoundation\Session
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class SessionTest extends \PHPUnit_Framework_TestCase
{

    protected $session;

    public function setUp()
    {
        $this->session = new Session(new MockArraySessionStorage());
    }

    public function testGetCartWithoutExistingCart()
    {
        $session = $this->session;

        $cart = $session->getCart();

        $this->assertNull($cart);
    }

    public function testGetCartWithExistingCartWithoutCustomerConnected()
    {
        $session = $this->session;

        $testCart = new Cart();
        $testCart->setToken(uniqid("testSessionGetCart1", true));
        $testCart->save();

        $session->setCart($testCart->getId());

        $cart = $session->getCart();

        $this->assertNotNull($cart);
        $this->assertInstanceOf("\Thelia\Model\Cart", $cart, '$cart must be an instance of Thelia\Model\Cart');
        $this->assertEquals($testCart->getToken(), $cart->getToken());

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

        $cart = $session->getCart();

        $this->assertNull($cart);
    }

    public function testGetCartWithExistingCartAndCustomerButWithoutReferenceToCustomerInCart()
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
        $testCart->setToken(uniqid("testSessionGetCart2", true));
        $testCart->save();

        $session->setCart($testCart->getId());

        $cart = $session->getCart();

        $this->assertNull($cart);
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

        $session->setCart($testCart->getId());

        $cart = $session->getCart();

        $this->assertNotNull($cart);
        $this->assertInstanceOf("\Thelia\Model\Cart", $cart, '$cart must be an instance of Thelia\Model\Cart');
    }

}
