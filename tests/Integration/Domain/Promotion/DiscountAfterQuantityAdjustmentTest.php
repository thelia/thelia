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

namespace Thelia\Tests\Integration\Domain\Promotion;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Operators;
use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Catalog\Quantity\QuantityRule;
use Thelia\Domain\Checkout\Exception\CartQuantitiesAdjustedException;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;

/**
 * A cart read back against the rules of its combinations can lose units without the
 * buyer touching it (CART_ITEM_QUANTITY_ADJUSTED): the discount is priced again on
 * what the cart now holds, not left at the one of the former quantities until the
 * next write.
 */
final class DiscountAfterQuantityAdjustmentTest extends ActionIntegrationPromotionTestCase
{
    private Product $product;

    private Cart $cart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = $this->product();
        $this->automaticPromotion(
            type: 'thelia.coupon.type.remove_x_percent',
            effects: ['percentage' => 10.0],
            conditions: $this->atLeastOneArticle(),
            title: '10 % off',
        );

        $this->cart = $this->newEmptyCart();
        $this->addItem($this->cart, $this->product, quantity: 7);
        // The cart priced the discount on the lines it had loaded before the addition.
        $this->cart->clearCartItems();
        $this->recomputeDiscount();
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheDiscountFollowsALineTheCheckoutBroughtDown(): void
    {
        $sevenUnits = $this->discount();
        $this->sellBySix();

        try {
            $this->getService(CartGuard::class)->checkQuantities($this->cart);
            self::fail('Seven is not sold by six.');
        } catch (CartQuantitiesAdjustedException) {
        }

        self::assertEqualsWithDelta($sevenUnits * 6 / 7, $this->discount(), 0.01, 'Ten per cent of six units, not of seven.');
    }

    public function testTheDiscountFollowsALineBroughtDownWhenTheCartIsRestored(): void
    {
        $sevenUnits = $this->discount();
        $this->sellBySix();

        // The visitor comes back with the cookie of the cart: the session does not know it yet.
        $this->session()->setSessionCart(null);
        $this->getService(RequestStack::class)->getMainRequest()?->cookies->set(
            ConfigQuery::read('cart.cookie_name', 'thelia_cart'),
            $this->cart->getToken(),
        );

        $restored = $this->session()->getSessionCart($this->dispatcher);

        self::assertSame($this->cart->getId(), $restored->getId());
        self::assertEqualsWithDelta($sevenUnits * 6 / 7, $this->discount(), 0.01, 'Ten per cent of six units, not of seven.');
    }

    public function testTheDiscountLeavesALineTheCheckoutRemoved(): void
    {
        $other = $this->product();
        $this->addItem($this->cart, $other, quantity: 1);
        $this->cart->clearCartItems();
        $this->recomputeDiscount();
        $eightUnits = $this->discount();

        $saleElements = $this->defaultPseFor($this->product);
        (new QuantityRule(minQuantity: 12.0))->applyTo($saleElements);
        $saleElements->save();

        try {
            $this->getService(CartGuard::class)->checkQuantities($this->cart);
            self::fail('Seven is below the smallest quantity sold.');
        } catch (CartQuantitiesAdjustedException) {
        }

        self::assertEqualsWithDelta($eightUnits / 8, $this->discount(), 0.01, 'Ten per cent of the one line left.');
    }

    private function sellBySix(): void
    {
        $saleElements = $this->defaultPseFor($this->product);
        (new QuantityRule(minQuantity: 2.0, step: 6.0))->applyTo($saleElements);
        $saleElements->save();
    }

    private function discount(): float
    {
        $this->cart->reload();

        $discount = (float) $this->cart->getDiscount();
        self::assertGreaterThan(0.0, $discount);

        return $discount;
    }

    private function atLeastOneArticle(): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(ConditionFactory::class)->build(
            'thelia.condition.match_for_x_articles',
            ['quantity' => Operators::SUPERIOR_OR_EQUAL],
            ['quantity' => 1],
        );

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }
}
