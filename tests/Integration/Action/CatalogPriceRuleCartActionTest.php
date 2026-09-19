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
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The cart line is the proof of the price the shop agreed to: a rule price is
 * written into it when the product is added, refreshed when the cart is touched,
 * and gone when the rule is.
 */
final class CatalogPriceRuleCartActionTest extends ActionIntegrationTestCase
{
    private const CATALOG_PRICE = 100.0;

    public function testAProductIsAddedToTheCartAtTheRulePrice(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $this->ruleOn($product, 20.0);

        $cartItem = $this->addToCart($this->factory->cart(), $product);

        self::assertSame(1, (int) $cartItem->getPromo());
        self::assertSame(80.0, (float) $cartItem->getPromoPrice());
        self::assertSame(80.0, $cartItem->getRealPrice());
        self::assertSame(self::CATALOG_PRICE, (float) $cartItem->getPrice(), 'the catalog price travels with the line');
    }

    public function testTheRulePriceReplacesTheCatalogPromoOnTheLine(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $pse = $this->defaultPseFor($product);
        $pse->setPromo(1)->save();
        ProductPriceQuery::create()->filterByProductSaleElementsId($pse->getId())->findOne()->setPromoPrice('50.000000')->save();
        $this->ruleOn($product, 20.0);

        self::assertSame(80.0, $this->addToCart($this->factory->cart(), $product)->getRealPrice());
    }

    public function testARuleThatEndedGivesTheLineItsCatalogPriceBackOnTheNextRefresh(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $customer = $this->newCustomer();
        $rule = $this->ruleOn($product, 20.0);

        $cart = $this->factory->cart($customer);
        self::assertSame(80.0, $this->addToCart($cart, $product)->getRealPrice());

        $rule->setEndDate(new \DateTime('-1 minute'))->save();
        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        $refreshed = $this->restoreCartAsCustomer($cart, $customer);

        self::assertSame(0, (int) $refreshed->getPromo());
        self::assertSame(self::CATALOG_PRICE, $refreshed->getRealPrice());
    }

    public function testSigningInAppliesAReservedRuleToAnAnonymousCart(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $customer = $this->newCustomer();
        $this->ruleOn($product, 30.0, ['audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS], $customer);

        $anonymousCart = $this->factory->cart();
        self::assertSame(self::CATALOG_PRICE, $this->addToCart($anonymousCart, $product)->getRealPrice(), 'reserved for somebody else');

        $signedIn = $this->restoreCartAsCustomer($anonymousCart, $customer);

        self::assertSame(1, (int) $signedIn->getPromo());
        self::assertSame(70.0, $signedIn->getRealPrice());
    }

    public function testTheCustomerDiscountAppliesOnTopOfTheRulePrice(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $customer = $this->newCustomer();
        $customer->setDiscount('10.000000')->save();
        $this->ruleOn($product, 20.0);

        $cartItem = $this->addToCart($this->factory->cart($customer), $product);

        // 100, 20% off by the rule, then the customer's own 10%: 72.
        self::assertSame(72.0, $cartItem->getRealPrice());
    }

    private function ruleOn(Product $product, float $percentage, array $overrides = [], ?Customer $customer = null): CatalogPriceRule
    {
        $rule = $this->factory->catalogPriceRule($overrides + ['active' => true, 'percentageValue' => $percentage]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());

        if (null !== $customer) {
            $this->factory->catalogPriceRuleCustomer($rule, $customer);
        }

        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        return $rule;
    }

    private function addToCart(Cart $cart, Product $product): CartItem
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

    private function restoreCartAsCustomer(Cart $cart, Customer $customer): CartItem
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
        $pse = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(true)->findOne();
        self::assertNotNull($pse);

        return $pse;
    }

    private function newCustomer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }
}
