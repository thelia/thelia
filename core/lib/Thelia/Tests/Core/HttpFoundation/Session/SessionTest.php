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
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Cart;
use Thelia\Model\Customer;

/**
 * Test the helpers adding in Session class
 *
 * Class SessionTest
 * @package Thelia\Tests\Core\HttpFoundation\Session
 * @author Manuel Raynaud <manu@thelia.net>
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
