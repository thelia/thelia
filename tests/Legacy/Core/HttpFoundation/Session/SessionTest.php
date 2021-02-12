<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Core\HttpFoundation\Session;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\Event\Cart\CartCreateEvent;
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
 * Test the helpers adding in Session class.
 *
 * Class SessionTest
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class SessionTest extends TestCase
{
    /** @var Session */
    protected $session;

    /** @var RequestStack */
    protected $requestStack;

    /** @var EventDispatcher */
    protected $dispatcher;

    protected $dispatcherNull;

    protected $cartToken;

    protected $cartAction;

    public function setUp(): void
    {
        $this->requestStack = new RequestStack();

        $request = new Request();

        $this->requestStack->push($request);

        $this->session = new Session(new MockArraySessionStorage());

        $request->setSession($this->session);

        $this->dispatcher = new EventDispatcher();

        $translator = new Translator($this->requestStack);

        $token = new TokenProvider($this->requestStack, $translator, 'test');

        $this->dispatcher->addSubscriber(new \Thelia\Action\Cart($this->requestStack, $token));

        $this->session->setSessionCart(null);

        $request->setSession($this->session);

        /* @var \Thelia\Action\Cart  cartAction */
        $this->cartAction = new \Thelia\Action\Cart(
            $this->requestStack,
            new TokenProvider($this->requestStack, $translator, 'baba au rhum')
        );

        $this->dispatcherNull = $this->createMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->dispatcher = $this->createMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $this->dispatcher
            ->expects($this->any())
            ->method('dispatch')
            ->willReturn(
                $this->returnCallback(
                    function ($type, $event): void {
                        if ($type == TheliaEvents::CART_RESTORE_CURRENT) {
                            $this->cartAction->restoreCurrentCart($event, null, $this->dispatcher);
                        } else {
                            $cartEvent = new CartCreateEvent();
                            $this->cartAction->createEmptyCart($cartEvent);
                        }
                    }
                )
            );
    }

    public function testGetCartWithoutExistingCartAndNoDispatcher(): void
    {
        $session = $this->session;

        $this->expectException(\InvalidArgumentException::class);
        $session->getSessionCart();
    }

    public function testGetCartWithoutExistingCartAndDeprecatedMethod(): void
    {
        $session = $this->session;

        $this->expectException(\InvalidArgumentException::class);
        @$session->getSessionCart();
    }

    public function testGetCartUnableToCreateCart(): void
    {
        $session = $this->session;

        $this->expectException(\LogicException::class);
        $session->getSessionCart($this->dispatcherNull);
    }

    public function testGetCartWithoutExistingCartNoCustomer(): void
    {
        $this->markTestSkipped('Mocked dispatcher don\'t work');
        $session = $this->session;

        $cart = $session->getSessionCart($this->dispatcher);

        $this->assertNotNull($cart);
        $this->assertInstanceOf("\Thelia\Model\Cart", $cart, '$cart must be an instance of Thelia\Model\Cart');
    }

    public function testGetCartWithExistingCustomerButNoCart(): void
    {
        $this->markTestSkipped('Mocked dispatcher don\'t work');

        $session = $this->session;

        //create a fake customer just for test. If not persists test fails !
        $customer = new Customer();
        $customer->setFirstname('john test session');
        $customer->setLastname('doe');
        $customer->setTitleId(1);
        $customer->save();

        $session->setCustomerUser($customer);

        $cart = $session->getSessionCart($this->dispatcher);

        $this->assertNotNull($cart);
        $this->assertEquals($customer->getId(), $cart->getCustomerId());
        $this->assertInstanceOf("\Thelia\Model\Cart", $cart, '$cart must be an instance of Thelia\Model\Cart');
    }

    public function testGetCartWithExistingCartAndCustomerButWithoutReferenceToCustomerInCart(): void
    {
        $this->markTestSkipped('Mocked dispatcher don\'t work');

        $session = $this->session;

        // create a fake customer just for test. If not persists test fails !
        $customer = new Customer();
        $customer->setFirstname('john test session');
        $customer->setLastname('doe');
        $customer->setTitleId(1);
        $customer->save();

        $session->setCustomerUser($customer);

        $testCart = new Cart();
        $testCart->setToken(uniqid('testSessionGetCart2', true));
        $testCart->save();

        $this->requestStack->getCurrentRequest()->cookies->set(ConfigQuery::read('cart.cookie_name', 'thelia_cart'), $testCart->getToken());

        $cart = $session->getSessionCart($this->dispatcher);

        $this->assertNotNull($cart);
        $this->assertEquals($customer->getId(), $cart->getCustomerId());
        $this->assertInstanceOf("\Thelia\Model\Cart", $cart, '$cart must be an instance of Thelia\Model\Cart');
    }

    public function testGetCartWithExistingCartAndCustomerAndReferencesEachOther(): void
    {
        $this->markTestSkipped('Mocked dispatcher don\'t work');

        $session = $this->session;

        //create a fake customer just for test. If not persists test fails !
        $customer = new Customer();
        $customer->setFirstname('john test session');
        $customer->setLastname('doe');
        $customer->setTitleId(1);
        $customer->save();

        $session->setCustomerUser($customer);

        $testCart = new Cart();
        $testCart->setToken(uniqid('testSessionGetCart3', true));
        $testCart->setCustomerId($customer->getId());
        $testCart->save();

        $this->requestStack->getCurrentRequest()->cookies->set(ConfigQuery::read('cart.cookie_name', 'thelia_cart'), $testCart->getToken());

        $cart = $session->getSessionCart($this->dispatcher);

        $this->assertNotNull($cart);
        $this->assertInstanceOf("\Thelia\Model\Cart", $cart, '$cart must be an instance of Thelia\Model\Cart');
    }

    public function testSetCurrency(): void
    {
        $session = new Session(new MockArraySessionStorage());

        $currentCurrency = (new Currency())->setId(99);
        $session->setCurrency($currentCurrency);
        $this->assertEquals($currentCurrency->getId(), $session->getCurrency()->getId());
    }

    public function testGetCurrencyWithParameterForceDefault(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $this->assertNull($session->getCurrency(false));
    }

    public function testGetCurrency(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $this->assertInstanceOf('Thelia\Model\Currency', $session->getCurrency());
    }

    public function testGetAdminEditionCurrencyWithCurrencyInSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $currentCurrency = (new Currency())->setId(99);
        $session->setAdminEditionCurrency($currentCurrency);
        $this->assertEquals($currentCurrency->getId(), $session->getAdminEditionCurrency()->getId());
    }

    public function testGetAdminEditionCurrencyWithNoCurrencyInSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $this->assertInstanceOf('Thelia\Model\Currency', $session->getAdminEditionCurrency());
    }
}
