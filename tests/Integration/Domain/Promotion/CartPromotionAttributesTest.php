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

use Thelia\Api\Service\DataAccess\AttributeAccessService;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Operators;
use Thelia\Domain\Promotion\Coupon\Type\BuyXGetY;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Product;

/**
 * What the front reads to name the promotions applying to the cart, instead of
 * showing one anonymous total.
 */
final class CartPromotionAttributesTest extends ActionIntegrationPromotionTestCase
{
    private AttributeAccessService $attributeAccess;

    protected function setUp(): void
    {
        parent::setUp();

        AttributeAccessService::clearCache();
        $this->attributeAccess = $this->getService(AttributeAccessService::class);
    }

    public function testAnEmptyCartHasNoDiscountToShow(): void
    {
        $this->newEmptyCart();

        self::assertSame([], $this->attributeAccess->attributeCart('discounts'));
        self::assertSame([], $this->attributeAccess->attributeCart('unavailable_promotions'));
    }

    public function testEachRetainedPromotionIsListedWithItsLabelAndAmount(): void
    {
        $this->automaticPromotion(
            effects: ['amount' => 5.0],
            conditions: $this->atLeastLines(1),
            title: 'Five euros off',
        );

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        $discounts = $this->attributeAccess->attributeCart('discounts');

        self::assertCount(1, $discounts);
        self::assertSame('Five euros off', $discounts[0]['label']);
        self::assertSame(5.0, $discounts[0]['taxed_amount']);
        self::assertSame($this->attributeAccess->attributeCart('taxed_discount'), $discounts[0]['taxed_amount']);
        self::assertEqualsWithDelta(
            $this->attributeAccess->attributeCart('discount'),
            $discounts[0]['amount'],
            0.01,
            'The untaxed amount of a single promotion is the untaxed cart discount.',
        );
        self::assertLessThan($discounts[0]['taxed_amount'], $discounts[0]['amount']);
    }

    /**
     * The list is exactly what the evaluation retained, no more. Two promotions
     * the merchant marked as combinable are both retained, each under its own
     * label, and their amounts add up to the cart discount.
     */
    public function testTheListHoldsExactlyThePromotionsTheEvaluationRetained(): void
    {
        $this->automaticPromotion(effects: ['amount' => 5.0], conditions: $this->atLeastLines(1), title: 'Five off');
        $this->automaticPromotion(effects: ['amount' => 3.0], conditions: $this->atLeastLines(1), title: 'Three off');

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product(['basePrice' => 100.0]));

        $discounts = $this->attributeAccess->attributeCart('discounts');

        self::assertSame(['Five off', 'Three off'], array_column($discounts, 'label'));
        self::assertSame(
            $this->attributeAccess->attributeCart('taxed_discount'),
            array_sum(array_column($discounts, 'taxed_amount')),
            'Whatever is retained, the listed amounts add up to the cart discount.',
        );
    }

    /**
     * A promotion with no public title still needs a name in the cart: the code
     * is the only other thing a customer can recognise it by.
     */
    public function testAPromotionWithoutATitleFallsBackToItsCode(): void
    {
        $coupon = $this->factory->coupon([
            'code' => 'NOTITLE-'.uniqid(),
            'title' => '',
            'conditions' => $this->atLeastLines(1),
        ]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        $this->session()->setConsumedCoupons([$coupon->getCode()]);
        $this->recomputeDiscount();

        self::assertSame([$coupon->getCode()], array_column($this->attributeAccess->attributeCart('discounts'), 'label'));
    }

    /**
     * A promotion whose gift is out of stock discounts nothing, so it has no
     * line in the discount list — it is announced through its own attribute.
     */
    public function testAnOfferWhoseGiftIsOutOfStockIsAnnouncedApart(): void
    {
        $trigger = $this->product();
        $this->buyXGetYPromotion($trigger, $this->product(['baseQuantity' => 0]), 'Two bought, one offered');

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);

        self::assertSame([], $this->attributeAccess->attributeCart('discounts'));
        self::assertSame(['Two bought, one offered'], $this->attributeAccess->attributeCart('unavailable_promotions'));
    }

    public function testAnOfferWhoseGiftIsInStockIsListedAsADiscount(): void
    {
        $trigger = $this->product();
        $this->buyXGetYPromotion($trigger, $this->product(), 'Two bought, one offered');

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);

        self::assertSame(
            ['Two bought, one offered'],
            array_column($this->attributeAccess->attributeCart('discounts'), 'label'),
        );
        self::assertSame([], $this->attributeAccess->attributeCart('unavailable_promotions'));
    }

    /**
     * The stored cart.discount is the authoritative figure: zero stored means
     * nothing to distribute, whatever the promotions would price right now.
     */
    public function testAStoredDiscountOfZeroShowsNoLines(): void
    {
        $this->automaticPromotion(effects: ['amount' => 5.0], conditions: $this->atLeastLines(1));

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        self::assertNotSame([], $this->attributeAccess->attributeCart('discounts'), 'Control: the promotion is listed.');

        $cart->setDiscount('0')->save();

        self::assertSame([], $this->attributeAccess->attributeCart('discounts'));
        self::assertSame(
            [0.0],
            array_column($this->attributeAccess->attributeCart('cart_items'), 'offered_taxed_discount'),
            'With nothing to distribute, no line carries an offered discount.',
        );
    }

    /**
     * When the stored discount and what the promotions price now disagree — the
     * stored one was capped, or the catalog moved — the stored figure wins and
     * the listed amounts are scaled onto it.
     */
    public function testTheListedAmountsAreProratedOntoTheStoredDiscount(): void
    {
        $this->automaticPromotion(effects: ['amount' => 5.0], conditions: $this->atLeastLines(1), title: 'Five euros off');

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        $cart->setDiscount('3')->save();

        $discounts = $this->attributeAccess->attributeCart('discounts');

        self::assertCount(1, $discounts);
        self::assertSame(3.0, $discounts[0]['taxed_amount']);
        self::assertSame($this->attributeAccess->attributeCart('taxed_discount'), $discounts[0]['taxed_amount']);
    }

    /**
     * Seven promotions share a stored discount of five cents: every share rounds up
     * to a cent, so the shares run out before the last lines. No listed amount may
     * then turn negative, with or without taxes.
     */
    public function testNoListedAmountIsNegativeWhenTheSharesRoundUpPastTheStoredDiscount(): void
    {
        for ($promotion = 1; $promotion <= 7; ++$promotion) {
            $this->automaticPromotion(effects: ['amount' => 1.0], conditions: $this->atLeastLines(1), title: 'One euro off '.$promotion);
        }

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        $cart->setDiscount('0.05')->save();

        $discounts = $this->attributeAccess->attributeCart('discounts');

        self::assertSame([0.01, 0.01, 0.01, 0.01, 0.01, 0.0, 0.0], array_column($discounts, 'taxed_amount'));
        self::assertSame([0.01, 0.01, 0.01, 0.01, 0.0, 0.0, 0.0], array_column($discounts, 'amount'));
    }

    /**
     * An automatic promotion carries no code: it has nothing to show in the
     * "your coupon" block, which lists what the customer typed in and can remove.
     */
    public function testAnAutomaticPromotionIsNeverListedAsATypedCoupon(): void
    {
        $this->automaticPromotion(effects: ['amount' => 5.0], conditions: $this->atLeastLines(1));

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        self::assertGreaterThan(0.0, $this->attributeAccess->attributeCart('taxed_discount'), 'Control: the promotion applies.');
        self::assertFalse($this->attributeAccess->attributeCoupon('has_coupons'));
        self::assertSame(0, $this->attributeAccess->attributeCoupon('coupon_count'));
        self::assertSame([], $this->attributeAccess->attributeCoupon('coupon_list'));
    }

    public function testATypedCouponIsListedByItsCode(): void
    {
        $coupon = $this->factory->coupon([
            'code' => 'TYPED-'.uniqid(),
            'conditions' => $this->atLeastLines(1),
        ]);

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());

        $this->session()->setConsumedCoupons([$coupon->getCode()]);
        $this->recomputeDiscount();

        self::assertTrue($this->attributeAccess->attributeCoupon('has_coupons'));
        self::assertSame(1, $this->attributeAccess->attributeCoupon('coupon_count'));
        self::assertSame([$coupon->getCode()], $this->attributeAccess->attributeCoupon('coupon_list'));
    }

    /**
     * The offered line exposes the taxed discount it carries, so the cart page
     * can strike ITS price instead of showing one anonymous total.
     */
    public function testAnOfferedLineExposesTheTaxedDiscountItCarries(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['basePrice' => 12.0]);
        $this->buyXGetYPromotion($trigger, $gift, 'Two bought, one offered');

        $cart = $this->newEmptyCart();
        $triggerLine = $this->addItem($cart, $trigger, quantity: 2);

        $offeredLine = CartItemQuery::create()
            ->filterByCartId($cart->getId())
            ->filterByIsOffered(1)
            ->findOne();
        self::assertNotNull($offeredLine);

        $items = array_column($this->attributeAccess->attributeCart('cart_items'), null, 'id');

        self::assertSame(1, $items[$offeredLine->getId()]['is_offered']);
        self::assertSame(
            $this->attributeAccess->attributeCart('taxed_discount'),
            $items[$offeredLine->getId()]['offered_taxed_discount'],
            'A fully offered gift carries the whole discount of its promotion.',
        );
        self::assertSame(
            0.0,
            $items[$triggerLine->getId()]['offered_taxed_discount'],
            'A regular line carries no offered discount.',
        );
    }

    /**
     * A theme that shows untaxed prices strikes the gift's untaxed price: the same discount,
     * without its tax, which is the untaxed discount of the cart when the gift is its only
     * discount.
     */
    public function testAnOfferedLineExposesTheUntaxedDiscountItCarries(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['basePrice' => 12.0]);
        $this->buyXGetYPromotion($trigger, $gift, 'Two bought, one offered');

        $cart = $this->newEmptyCart();
        $triggerLine = $this->addItem($cart, $trigger, quantity: 2);

        $offeredLine = CartItemQuery::create()
            ->filterByCartId($cart->getId())
            ->filterByIsOffered(1)
            ->findOne();
        self::assertNotNull($offeredLine);

        $items = array_column($this->attributeAccess->attributeCart('cart_items'), null, 'id');
        $offered = $items[$offeredLine->getId()];

        self::assertGreaterThan(0.0, $offered['offered_discount']);
        self::assertLessThan($offered['offered_taxed_discount'], $offered['offered_discount'], 'The taxed gift carries tax: its untaxed discount is smaller.');
        self::assertEqualsWithDelta(
            $this->attributeAccess->attributeCart('discount'),
            $offered['offered_discount'],
            0.01,
            'A fully offered gift carries the whole untaxed discount of its promotion.',
        );
        self::assertEqualsWithDelta(12.0, $offered['offered_discount'], 0.01, 'The gift costs 12 untaxed.');
        self::assertSame(0.0, $items[$triggerLine->getId()]['offered_discount'], 'A regular line carries no offered discount.');
    }

    public function testTheOfferedLineDiscountFollowsTheProrationOfTheStoredDiscount(): void
    {
        $trigger = $this->product();
        $gift = $this->product(['basePrice' => 12.0]);
        $this->buyXGetYPromotion($trigger, $gift, 'Two bought, one offered');

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);

        $cart->setDiscount('3')->save();

        $offeredItems = array_filter(
            $this->attributeAccess->attributeCart('cart_items'),
            static fn (array $item): bool => 1 === $item['is_offered'],
        );

        self::assertSame(
            [3.0],
            array_values(array_column($offeredItems, 'offered_taxed_discount')),
            'The line discount is scaled onto the stored discount, like the list.',
        );
    }

    /**
     * A theme prints the line of a gift from the figures the cart publishes, in the display it
     * is read in: the whole line, the discount it carries and what is left to pay, no sum made
     * by the theme.
     */
    public function testAFullyOfferedLineIsPublishedWithNothingLeftToPay(): void
    {
        [$cart, $triggerLine, $offeredLine] = $this->cartWithAnOfferedGift();

        $items = array_column($this->attributeAccess->attributeCart('cart_items'), null, 'id');
        $offered = $items[$offeredLine->getId()];

        self::assertEqualsWithDelta(12.0, $offered['line_total_untaxed'], 0.0001);
        self::assertEqualsWithDelta($this->taxedTotalOf($offeredLine), $offered['line_total_taxed'], 0.0001);
        self::assertEqualsWithDelta($offered['offered_taxed_discount'], $offered['offered_effective_taxed'], 0.0001);
        self::assertEqualsWithDelta($offered['offered_discount'], $offered['offered_effective_untaxed'], 0.0001);
        self::assertEqualsWithDelta(0.0, $offered['line_total_after_discount_taxed'], 0.01);
        self::assertEqualsWithDelta(0.0, $offered['line_total_after_discount_untaxed'], 0.01);

        $regular = $items[$triggerLine->getId()];
        self::assertSame(0.0, $regular['offered_effective_taxed']);
        self::assertSame(0.0, $regular['offered_effective_untaxed']);
        self::assertSame($regular['line_total_taxed'], $regular['line_total_after_discount_taxed'], 'A regular line pays its whole total.');
        self::assertSame($regular['line_total_untaxed'], $regular['line_total_after_discount_untaxed']);
    }

    public function testAPartlyOfferedLineLeavesWhatTheDiscountDoesNotTake(): void
    {
        [$cart, , $offeredLine] = $this->cartWithAnOfferedGift();

        // The promotion grants the whole gift, the cart stores less: the line carries the share.
        $cart->setDiscount('3')->save();

        $offered = array_column($this->attributeAccess->attributeCart('cart_items'), null, 'id')[$offeredLine->getId()];

        $taxedTotal = $this->taxedTotalOf($offeredLine);
        self::assertEqualsWithDelta(3.0, $offered['offered_effective_taxed'], 0.0001);
        self::assertEqualsWithDelta(round($taxedTotal - 3.0, 2), $offered['line_total_after_discount_taxed'], 0.0001);
        self::assertGreaterThan(0.0, $offered['line_total_after_discount_taxed']);
        self::assertGreaterThan(0.0, $offered['offered_effective_untaxed']);
        self::assertLessThan($offered['offered_effective_taxed'], $offered['offered_effective_untaxed'], 'The untaxed part of the discount is the smaller.');
        self::assertEqualsWithDelta(
            round(12.0 - $offered['offered_effective_untaxed'], 2),
            $offered['line_total_after_discount_untaxed'],
            0.0001,
        );
    }

    public function testADiscountWorthMoreThanTheLineTakesTheLineAndNoMore(): void
    {
        [$cart, , $offeredLine] = $this->cartWithAnOfferedGift();

        $cart->setDiscount('100')->save();

        $offered = array_column($this->attributeAccess->attributeCart('cart_items'), null, 'id')[$offeredLine->getId()];

        self::assertGreaterThan($offered['line_total_taxed'], $offered['offered_taxed_discount'], 'The promotion is configured to take off more than the line is worth.');
        self::assertEqualsWithDelta($offered['line_total_taxed'], $offered['offered_effective_taxed'], 0.0001);
        self::assertEqualsWithDelta($offered['line_total_untaxed'], $offered['offered_effective_untaxed'], 0.0001);
        self::assertSame(0.0, $offered['line_total_after_discount_taxed'], 'The buyer is never shown a negative price.');
        self::assertSame(0.0, $offered['line_total_after_discount_untaxed']);
    }

    /**
     * @return array{0: Cart, 1: CartItem, 2: CartItem}
     */
    private function cartWithAnOfferedGift(): array
    {
        $trigger = $this->product();
        $gift = $this->product(['basePrice' => 12.0]);
        $this->buyXGetYPromotion($trigger, $gift, 'Two bought, one offered');

        $cart = $this->newEmptyCart();
        $triggerLine = $this->addItem($cart, $trigger, quantity: 2);

        $offeredLine = CartItemQuery::create()
            ->filterByCartId($cart->getId())
            ->filterByIsOffered(1)
            ->findOne();
        self::assertNotNull($offeredLine);

        return [$cart, $triggerLine, $offeredLine];
    }

    private function taxedTotalOf(CartItem $line): float
    {
        $taxEngine = $this->getService(TaxEngine::class);

        return round($line->getTotalRealTaxedPrice($taxEngine->getDeliveryCountry(), $taxEngine->getDeliveryState()), 2);
    }

    private function buyXGetYPromotion(Product $trigger, Product $gift, string $title): void
    {
        $this->automaticPromotion(
            type: 'thelia.coupon.type.buy_x_get_y',
            effects: [
                BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_PRODUCT,
                BuyXGetY::TRIGGER_IDS_FIELD => [$trigger->getId()],
                BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
                BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_PRODUCT,
                BuyXGetY::TARGET_PRODUCT_ID_FIELD => $gift->getId(),
                BuyXGetY::OFFERED_QUANTITY_FIELD => 1,
                BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_FREE,
                BuyXGetY::DISCOUNT_VALUE_FIELD => 0.0,
            ],
            conditions: $this->atLeastLines(1),
            title: $title,
        );
    }

    private function atLeastLines(int $lines): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(ConditionFactory::class)->build(
            'thelia.condition.match_for_x_articles',
            ['quantity' => Operators::SUPERIOR_OR_EQUAL],
            ['quantity' => $lines],
        );

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }
}
