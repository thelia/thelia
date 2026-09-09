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

namespace Thelia\Tests\Integration\Action;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartRestoreEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Sale;
use Thelia\Model\SaleCustomerQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A reserved price is only a promise until the customer puts the product in
 * their cart: the cart line is what the order is built from, so the line is
 * where the price the shop agreed to has to be written down.
 *
 * The catalog price is 100 excluding tax, the shop's default tax rule adds 20%,
 * and every reserved operation below takes 10% off the taxed price — which comes
 * back to 90 excluding tax.
 */
final class ReservedSaleCartActionTest extends ActionIntegrationTestCase
{
    private const CATALOG_PRICE = 100.0;

    private const RESERVED_PRICE = 90.0;

    public function testAProductIsAddedToTheCartAtTheReservedPrice(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $customer = $this->newCustomer();
        $this->reservedOperationOn($product, $currency, $customer);

        $cartItem = $this->addToCart($this->factory->cart($customer), $product);

        self::assertSame(1, (int) $cartItem->getPromo(), 'the line is flagged as a special offer');
        self::assertSame(self::RESERVED_PRICE, (float) $cartItem->getPromoPrice());
        self::assertSame(self::RESERVED_PRICE, $cartItem->getRealPrice());
    }

    public function testACustomerTheOperationDoesNotNamePaysTheCatalogPrice(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $this->reservedOperationOn($product, $currency, $this->newCustomer());

        $cartItem = $this->addToCart($this->factory->cart($this->newCustomer()), $product);

        self::assertSame(0, (int) $cartItem->getPromo());
        self::assertSame(self::CATALOG_PRICE, $cartItem->getRealPrice());
    }

    public function testAVisitorPaysTheCatalogPrice(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $this->reservedOperationOn($product, $currency, $this->newCustomer());

        $cartItem = $this->addToCart($this->factory->cart(), $product);

        self::assertSame(0, (int) $cartItem->getPromo());
        self::assertSame(self::CATALOG_PRICE, $cartItem->getRealPrice());
    }

    /**
     * A customer taken out of the selection loses the price on the next refresh:
     * a line written weeks ago is not a price the shop still owes.
     */
    public function testACustomerRemovedFromTheSelectionFallsBackToTheCatalogPrice(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $customer = $this->newCustomer();
        $sale = $this->reservedOperationOn($product, $currency, $customer);

        $cart = $this->factory->cart($customer);
        self::assertSame(self::RESERVED_PRICE, $this->addToCart($cart, $product)->getRealPrice());

        SaleCustomerQuery::create()->filterBySaleId($sale->getId())->delete();

        $refreshed = $this->restoreCartAsCustomer($cart, $customer);

        self::assertSame(0, (int) $refreshed->getPromo());
        self::assertSame(self::CATALOG_PRICE, $refreshed->getRealPrice());
    }

    public function testAnOperationThatHasEndedFallsBackToTheCatalogPrice(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $customer = $this->newCustomer();
        $sale = $this->reservedOperationOn($product, $currency, $customer);

        $cart = $this->factory->cart($customer);
        self::assertSame(self::RESERVED_PRICE, $this->addToCart($cart, $product)->getRealPrice());

        $sale->setEndDate(new \DateTime('-1 minute'))->save();

        $refreshed = $this->restoreCartAsCustomer($cart, $customer);

        self::assertSame(0, (int) $refreshed->getPromo());
        self::assertSame(self::CATALOG_PRICE, $refreshed->getRealPrice());
    }

    /**
     * Everything in a cart filled before signing in has to be repriced at sign-in,
     * not only when the customer carries a discount rate.
     */
    public function testSigningInAppliesTheReservedPriceToAnAnonymousCart(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $customer = $this->newCustomer();
        $this->reservedOperationOn($product, $currency, $customer);

        $anonymousCart = $this->factory->cart();
        $item = $this->addToCart($anonymousCart, $product);
        self::assertSame(self::CATALOG_PRICE, $item->getRealPrice(), 'nothing is reserved for nobody');

        $signedIn = $this->restoreCartAsCustomer($anonymousCart, $customer);

        self::assertSame(1, (int) $signedIn->getPromo());
        self::assertSame(self::RESERVED_PRICE, $signedIn->getRealPrice());
    }

    private function reservedOperationOn(Product $product, Currency $currency, Customer $customer): Sale
    {
        $sale = $this->factory->sale([
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'priceOffsetType' => Sale::OFFSET_TYPE_PERCENTAGE,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);

        $this->factory->saleOffsetCurrency($sale, $currency, 10.0);
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $customer);

        return $sale;
    }

    private function addToCart(Cart $cart, Product $product): \Thelia\Model\CartItem
    {
        $event = new CartEvent($cart);
        $event
            ->setProductId($product->getId())
            ->setProductSaleElementsId($this->defaultPseFor($product)->getId())
            ->setQuantity(1)
            ->setNewness(true)
            ->setAppend(true);

        $this->dispatch($event, TheliaEvents::CART_ADDITEM);

        return $event->getCartItem();
    }

    /**
     * Restores the cart the way a request does — through the cart cookie, with the
     * customer in session — and hands back the single line of the restored cart.
     */
    private function restoreCartAsCustomer(Cart $cart, Customer $customer): \Thelia\Model\CartItem
    {
        $this->mainRequest()->cookies->set(
            ConfigQuery::read('cart.cookie_name', 'thelia_cart'),
            $cart->getToken(),
        );
        $this->getService(SecurityContext::class)->setCustomerUser($customer);

        $event = new CartRestoreEvent();
        $this->dispatch($event, TheliaEvents::CART_RESTORE_CURRENT);

        $restored = $event->getCart();
        $restored->clearCartItems();

        return $restored->getCartItems()->getFirst()
            ?? self::fail('The restored cart lost its only line.');
    }

    private function mainRequest(): Request
    {
        return $this->getService(RequestStack::class)->getMainRequest();
    }

    private function catalogProduct(Currency $currency): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
            ['baseQuantity' => 100, 'basePrice' => self::CATALOG_PRICE],
        );
    }

    private function defaultPseFor(Product $product): ProductSaleElements
    {
        return ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();
    }

    private function newCustomer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }
}
