<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Integration\Domain\Catalog\Restriction;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Cart\Exception\CartLineUnavailableException;
use Thelia\Domain\Cart\Exception\InvalidCartException;
use Thelia\Domain\Cart\Exception\ProductNotAvailableException;
use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CatalogRestriction;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Product;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The cart holds what the catalog restrictions of its customer let through: an
 * addition of a product outside the assortment is refused (a 422 on the front API), the line of
 * a product that went out of it is taken out when the cart is found again, with a sentence that
 * names the products and nothing of the rule, and the checkout refuses a cart that still holds it.
 */
final class CatalogRestrictionCartTest extends ActionIntegrationTestCase
{
    private CustomerGroup $buyers;

    private Product $allowed;

    private Product $outside;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = $this->factory->currency();
        $taxRule = $this->factory->taxRule();
        $this->allowed = $this->factory->product($this->factory->category(), $taxRule, $currency, ['baseQuantity' => 10]);
        $this->outside = $this->factory->product($this->factory->category(), $taxRule, $currency, ['baseQuantity' => 10]);
        $this->buyers = $this->factory->customerGroup('buyers');
        (new CatalogRestriction())->setCustomerGroupId($this->buyers->getId())->setProductId($this->allowed->getId())->setMode('allow')->save($this->getPropelConnection());
        $this->forget();
    }

    protected function tearDown(): void
    {
        $this->session()->setCustomerUser(null);
        $this->session()->setSessionCart(null);
        $this->getService(RequestStack::class)->getMainRequest()?->cookies->remove(ConfigQuery::read('cart.cookie_name', 'thelia_cart'));
        ConfigQuery::resetCache();
        $this->forget();

        parent::tearDown();
    }

    public function testAProductOutsideTheAssortmentIsRefusedAndTheCartIsLeftAsItWas(): void
    {
        $this->signInAs($this->buyer());
        $cart = $this->factory->cart();

        try {
            $this->add($cart, $this->outside);
            self::fail('A product outside the assortment must not enter the cart.');
        } catch (ProductNotAvailableException $refusal) {
            self::assertInstanceOf(InvalidCartException::class, $refusal, 'The front API answers it with a 422.');
        }

        self::assertSame(0, $this->lineCount($cart));
    }

    public function testAProductOfTheAssortmentIsAddedAndAnotherGroupAddsBoth(): void
    {
        $this->signInAs($this->buyer());
        $cart = $this->factory->cart();
        $this->add($cart, $this->allowed);

        self::assertSame(1, $this->lineCount($cart));

        $this->signInAs($this->factory->customer($this->factory->customerTitle()));
        $other = $this->factory->cart();
        $this->add($other, $this->allowed);
        $this->add($other, $this->outside);

        self::assertSame(2, $this->lineCount($other));
    }

    public function testACartFoundAgainLosesTheLinesOutsideTheAssortmentAndTheSessionIsToldToPriceItAgain(): void
    {
        ConfigQuery::write('cart.use_persistent_cookie', '1');
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->allowed);
        $this->factory->cartItem($cart, $this->outside);
        $this->getService(RequestStack::class)->getMainRequest()->cookies->set(ConfigQuery::read('cart.cookie_name', 'thelia_cart'), $cart->getToken());
        $this->session()->setCustomerUser($this->buyer());
        $this->session()->setSessionCart(null);
        $this->forget();

        $removed = 0;
        $listener = static function () use (&$removed): void {
            ++$removed;
        };
        $this->dispatcher->addListener(TheliaEvents::CART_LINES_REMOVED, $listener);

        try {
            $loaded = $this->session()->getSessionCart($this->dispatcher);
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::CART_LINES_REMOVED, $listener);
        }

        $lines = CartItemQuery::create()->filterByCartId($loaded->getId())->find();
        self::assertCount(1, $lines);
        self::assertSame((int) $this->allowed->getId(), (int) $lines->getFirst()->getProductId());
        self::assertSame(1, $removed, 'CART_LINES_REMOVED is dispatched once the session holds the cart.');

        $notices = $this->session()->getFlashBag()->peek('error');
        self::assertCount(1, $notices);
        self::assertStringNotContainsStringIgnoringCase('restriction', $notices[0]);
        self::assertStringNotContainsStringIgnoringCase('allow', $notices[0]);
        self::assertStringNotContainsStringIgnoringCase('assortment', $notices[0]);
    }

    public function testACartFoundAgainWhoseLinesAllStandIsLeftAlone(): void
    {
        ConfigQuery::write('cart.use_persistent_cookie', '1');
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->allowed);
        $this->getService(RequestStack::class)->getMainRequest()->cookies->set(ConfigQuery::read('cart.cookie_name', 'thelia_cart'), $cart->getToken());
        $this->session()->setCustomerUser($this->buyer());
        $this->forget();

        $event = new CartRestoreEvent();
        $this->dispatch($event, TheliaEvents::CART_RESTORE_CURRENT);

        self::assertFalse($event->haveLinesBeenRemoved());
        self::assertSame(1, $this->lineCount($event->getCart()));
    }

    public function testTheCheckoutRefusesALineOutsideTheAssortment(): void
    {
        $cart = $this->factory->cart();
        $this->factory->cartItem($cart, $this->outside);
        $this->signInAs($this->buyer());

        $this->expectException(CartLineUnavailableException::class);

        $this->getService(CartGuard::class)->checkLinesAreAvailable($cart);
    }

    private function add(Cart $cart, Product $product): void
    {
        $this->getService(CartFacade::class)->addItem(new CartItemAddDTO(
            $cart,
            (int) $product->getId(),
            (int) $product->getDefaultSaleElements()->getId(),
        ));
    }

    private function lineCount(Cart $cart): int
    {
        return CartItemQuery::create()->filterByCartId($cart->getId())->count();
    }

    private function buyer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $this->buyers]);
    }

    private function signInAs(Customer $customer): void
    {
        $this->session()->setCustomerUser($customer);
        $this->forget();
    }

    private function session(): Session
    {
        $session = $this->getService(RequestStack::class)->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }

    private function forget(): void
    {
        $this->getService('services_resetter')->reset();
    }
}
