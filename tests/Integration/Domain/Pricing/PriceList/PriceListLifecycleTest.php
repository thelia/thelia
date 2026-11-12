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

namespace Thelia\Tests\Integration\Domain\Pricing\PriceList;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Cart\Exception\CartPricesChangedException;
use Thelia\Domain\Cart\Service\CartPriceGuard;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\PriceList;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * What a cart holds follows the life of the price lists around it: a list turned off or deleted
 * takes its price out of the carts that still hold it, and a customer who signs in gets, on the
 * cart they filled as a visitor, the price of their list.
 *
 * The example: the list PRO prices A at 80 for the group PRO, the catalog at 100. A member puts A
 * in the cart at 80; the merchant turns PRO off, or deletes it: the next gesture on the cart, and
 * the payment, find A at 100. A visitor puts A at 100, signs in as a member: the line is 80.
 */
final class PriceListLifecycleTest extends ActionIntegrationTestCase
{
    use PriceListFixtures;

    private Currency $currency;

    private CustomerGroup $group;

    private Customer $member;

    private Product $product;

    private ProductSaleElements $pse;

    private PriceList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::getDefaultCurrency();
        $this->group = $this->factory->customerGroup('pro-lifecycle');
        $this->member = $this->memberOf($this->group);
        $this->product = $this->pricedProduct(100.0);
        $this->pse = $this->product->getDefaultSaleElements();
        $this->list = $this->priceList('PRO-LIFECYCLE', $this->currency);
        $this->assignToGroup($this->list, $this->group);
        $this->priceListItem($this->list, $this->pse, '80');
        $this->forgetRequestMemos();
    }

    protected function tearDown(): void
    {
        $this->session()->setCustomerUser(null);
        $this->session()->setSessionCart(null);
        $this->getService(RequestStack::class)->getMainRequest()?->cookies->remove(ConfigQuery::read('cart.cookie_name', 'thelia_cart'));
        ConfigQuery::resetCache();
        $this->forgetRequestMemos();

        parent::tearDown();
    }

    public function testTheLineKeepsTheTraceOfTheListThatPricedItAndLosesItWithThePrice(): void
    {
        $cart = $this->factory->cart($this->member, ['currency' => $this->currency]);

        $line = $this->addToCart($cart, 1);
        self::assertSame('80.000000', $line->getPrice());
        self::assertSame((int) $this->list->getId(), $this->reloaded($line)->getPriceListId());

        $this->list->setActive(0)->save($this->getPropelConnection());
        $this->forgetRequestMemos();
        $this->addToCart($cart, 1);

        self::assertNull($this->reloaded($line)->getPriceListId());
    }

    public function testALineHeldAtTheNegotiatedPriceGoesBackToTheCatalogWhenTheLastListIsTurnedOff(): void
    {
        $cart = $this->factory->cart($this->member, ['currency' => $this->currency]);
        $line = $this->addToCart($cart, 1);
        self::assertSame('80.000000', $line->getPrice());

        $this->list->setActive(0)->save($this->getPropelConnection());
        $this->forgetRequestMemos();
        $this->addToCart($cart, 1);

        self::assertSame('100.000000', $this->reloaded($line)->getPrice(), 'no list left to give 80: the next gesture on the cart prices it again');
    }

    public function testALineHeldAtTheNegotiatedPriceGoesBackToTheCatalogWhenTheLastListIsDeleted(): void
    {
        $cart = $this->factory->cart($this->member, ['currency' => $this->currency]);
        $line = $this->addToCart($cart, 1);

        $this->list->delete($this->getPropelConnection());
        $this->forgetRequestMemos();
        $this->addToCart($cart, 1);

        self::assertSame('100.000000', $this->reloaded($line)->getPrice());
    }

    public function testThePaymentRefusesACartHeldAtThePriceOfAListTurnedOff(): void
    {
        $cart = $this->factory->cart($this->member, ['currency' => $this->currency]);
        $this->addToCart($cart, 1);
        $this->list->setActive(0)->save($this->getPropelConnection());
        $this->forgetRequestMemos();
        $cart->clearCartItems();

        $this->expectException(CartPricesChangedException::class);

        $this->getService(CartPriceGuard::class)->settleOrRefuse($cart, $this->currency, $this->member);
    }

    public function testOnceTheLineIsPricedAgainTheShopIsAsQuietAsIfItNeverHadAList(): void
    {
        $cart = $this->factory->cart($this->member, ['currency' => $this->currency]);
        $this->addToCart($cart, 1);
        $this->list->setActive(0)->save($this->getPropelConnection());
        $this->forgetRequestMemos();
        self::assertTrue($this->getService(CatalogActivityProbe::class)->read()->customersMayBePricedApart(), 'the line still holds the trace');

        $this->addToCart($cart, 1);
        $this->forgetRequestMemos();

        $activity = $this->getService(CatalogActivityProbe::class)->read();
        self::assertFalse($activity->hasCartLinesPricedByPriceLists);
        self::assertFalse($activity->customersMayBePricedApart());
    }

    public function testACartFilledAsAVisitorIsPricedAtTheListOfTheCustomerWhoSignsIn(): void
    {
        $cart = $this->factory->cart(null, ['currency' => $this->currency]);
        $line = $this->factory->cartItem($cart, $this->product, $this->pse, ['price' => '100.000000', 'promoPrice' => '100.000000']);
        $this->session()->setSessionCart($cart);
        $this->forgetRequestMemos();

        $this->dispatch(new CustomerLoginEvent($this->member), TheliaEvents::CUSTOMER_LOGIN);

        self::assertSame('80.000000', $this->reloaded($line)->getPrice(), 'the line of the cart is the one the product page shows');
    }

    private function addToCart(Cart $cart, int $quantity): CartItem
    {
        $event = new CartEvent($cart);
        $event
            ->setProductId($this->product->getId())
            ->setProductSaleElementsId($this->pse->getId())
            ->setQuantity($quantity)
            ->setNewness(true)
            ->setAppend(true);
        $this->dispatch($event, TheliaEvents::CART_ADDITEM);

        return $event->getCartItem() ?? self::fail('The line was not added.');
    }

    private function reloaded(CartItem $line): CartItem
    {
        CartItemTableMap::clearInstancePool();

        return CartItemQuery::create()->findPk($line->getId()) ?? self::fail('The line is gone.');
    }

    private function session(): Session
    {
        $session = $this->getService(RequestStack::class)->getCurrentRequest()?->getSession();
        self::assertInstanceOf(Session::class, $session);

        return $session;
    }
}
