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

namespace Thelia\Tests\Integration\Domain\Pricing\Rule;

use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleToggleActivityEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * What a running catalog price rule may and may not do to an open cart.
 *
 * Settling the cart against the rules touches every line it finds, so a line a
 * promotion placed as a gift has to be left where it is: its price is the one the
 * promotion decided, not one the catalog can give back.
 */
final class CatalogPriceRuleCartSettlementTest extends ActionIntegrationTestCase
{
    private const CATALOG_PRICE = 100.0;

    public function testARunningRuleLeavesAnOfferedLineAtThePriceThePromotionGaveIt(): void
    {
        $currency = $this->factory->currency();
        $gift = $this->catalogProduct($currency);
        $bought = $this->catalogProduct($currency);

        // A rule wide enough to cover both products, the gift included.
        $rule = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 20.0]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $gift->getId());
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $bought->getId());
        $this->dispatch(
            new CatalogPriceRuleToggleActivityEvent($rule->getId(), true),
            TheliaEvents::CATALOG_PRICE_RULE_TOGGLE_ACTIVITY,
        );

        $cart = $this->factory->cart();
        $offered = $this->offeredLine($cart, $gift);

        // The rule really does price this product, so the check below is not vacuous:
        // a line the settlement touches would come back at 80, not at 0.
        $this->newRequest();
        $paid = $this->addToCart($cart, $bought);
        self::assertSame(80.0, $paid->getRealPrice(), 'the rule prices the line the customer pays for');

        // The visitor touches the cart again, which settles its prices.
        $this->newRequest();
        $this->addToCart($cart, $bought);

        $offered->reload();

        self::assertSame(1, (int) $offered->getIsOffered(), 'the gift is still a gift');
        self::assertSame(0.0, $offered->getRealPrice(), 'the rule did not put a price back on the gift');
    }

    /**
     * The per-request memoised pricing checks are what a new request starts from.
     */
    private function newRequest(): void
    {
        $this->getService(PricingActivityChecker::class)->reset();
        $this->getService(EffectivePriceCatalog::class)->reset();
    }

    /**
     * A line as an automatic promotion places it: offered, free, and flagged as a
     * special offer like any discounted line.
     */
    private function offeredLine(Cart $cart, Product $product): CartItem
    {
        $cartItem = (new CartItem())
            ->setCart($cart)
            ->setProduct($product)
            ->setProductSaleElements($this->defaultPseFor($product))
            ->setQuantity(1)
            ->setPrice((string) self::CATALOG_PRICE)
            ->setPromoPrice('0')
            ->setPromo(1)
            ->setIsOffered(1);
        $cartItem->save();

        return $cartItem;
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
}
