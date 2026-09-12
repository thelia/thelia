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

namespace Thelia\Tests\Unit\Domain\Promotion;

use PHPUnit\Framework\TestCase;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\Type\BuyXGetY;
use Thelia\Domain\Promotion\Coupon\Type\CouponAbstract;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Country;

/**
 * exec() prices an offer against the cart without ever writing to it. Every
 * case here is arithmetic on a cart double: the category scope is the one
 * branch that reads product_category and is covered in the integration layer.
 */
final class BuyXGetYTest extends TestCase
{
    public function testAFullLotOfTheTriggeringProductOffersOneUnitForFree(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 3, 'price' => 10.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 3,
        ]);

        self::assertSame(10.0, $coupon->exec());
    }

    /**
     * The whole cart as the lot: it forms one, never several. Paired with a cart-total
     * condition this is what « one gift from eighty euros » needs — the scope that
     * divides would hand out one gift per article.
     */
    public function testTheWholeCartFormsASingleLotWhateverItHolds(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 4, 'price' => 10.0],
            ['productId' => 9, 'quantity' => 3, 'price' => 20.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_CART,
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
            BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_CHEAPEST,
            BuyXGetY::OFFERED_QUANTITY_FIELD => 1,
        ]);

        self::assertSame(
            10.0,
            $coupon->exec(),
            'Seven articles form one lot, so exactly one unit is offered, the cheapest.',
        );
    }

    public function testTheWholeCartOffersNothingBelowTheMinimumNumberOfArticles(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 1, 'price' => 10.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_CART,
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
            BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_CHEAPEST,
            BuyXGetY::OFFERED_QUANTITY_FIELD => 1,
        ]);

        self::assertSame(0.0, $coupon->exec(), 'The triggering quantity is a floor, not a divisor.');
    }

    /**
     * The cart scope names nothing, so the guard that refuses a rule without a
     * selection must not refuse it.
     */
    public function testTheWholeCartNeedsNoProductOrCategoryNamed(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 2, 'price' => 15.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_CART,
            BuyXGetY::TRIGGER_IDS_FIELD => [],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
            BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_CHEAPEST,
            BuyXGetY::OFFERED_QUANTITY_FIELD => 1,
        ]);

        self::assertSame(15.0, $coupon->exec());
    }

    public function testACartShortOfAFullLotOffersNothing(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 2, 'price' => 10.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 3,
        ]);

        self::assertSame(0.0, $coupon->exec());
    }

    public function testEveryCompleteLotIsCounted(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 7, 'price' => 10.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 3,
        ]);

        // 7 units make 2 lots, the seventh unit is not enough for a third.
        self::assertSame(20.0, $coupon->exec());
    }

    public function testAnOfferedQuantityAboveOneIsGrantedPerLot(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 6, 'price' => 10.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 3,
            BuyXGetY::OFFERED_QUANTITY_FIELD => 2,
        ]);

        self::assertSame(40.0, $coupon->exec());
    }

    public function testASelectionOfProductsFormsLotsTogether(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 1, 'price' => 10.0],
            ['productId' => 8, 'quantity' => 1, 'price' => 20.0],
            ['productId' => 9, 'quantity' => 1, 'price' => 30.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_SELECTION,
            BuyXGetY::TRIGGER_IDS_FIELD => [7, 8, 9],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 3,
        ]);

        // One lot across the three products, and the cheapest unit is the one offered.
        self::assertSame(10.0, $coupon->exec());
    }

    public function testAProductOutsideTheSelectionNeverFeedsALot(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 2, 'price' => 10.0],
            ['productId' => 42, 'quantity' => 5, 'price' => 10.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 3,
        ]);

        self::assertSame(0.0, $coupon->exec());
    }

    public function testTheCheapestTargetDiscountsTheCheapestUnitsOfTheLot(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 1, 'price' => 50.0],
            ['productId' => 8, 'quantity' => 1, 'price' => 5.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_SELECTION,
            BuyXGetY::TRIGGER_IDS_FIELD => [7, 8],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
            BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_CHEAPEST,
        ]);

        self::assertSame(5.0, $coupon->exec());
    }

    public function testAPercentageDiscountsAShareOfTheOfferedUnits(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 2, 'price' => 40.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
            BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_PERCENTAGE,
            BuyXGetY::DISCOUNT_VALUE_FIELD => 25.0,
        ]);

        self::assertSame(10.0, $coupon->exec());
    }

    public function testAFlatAmountIsGrantedPerOfferedUnit(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 4, 'price' => 40.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
            BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_AMOUNT,
            BuyXGetY::DISCOUNT_VALUE_FIELD => 3.0,
        ]);

        // Two lots, one unit offered each, three euros off each of them.
        self::assertSame(6.0, $coupon->exec());
    }

    public function testAFlatAmountNeverExceedsWhatTheOfferedUnitsCost(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 2, 'price' => 4.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
            BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_AMOUNT,
            BuyXGetY::DISCOUNT_VALUE_FIELD => 100.0,
        ]);

        self::assertSame(4.0, $coupon->exec());
    }

    public function testAnOfferedLineNeverFeedsALot(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 2, 'price' => 10.0],
            ['productId' => 7, 'quantity' => 1, 'price' => 10.0, 'isOffered' => 1],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 3,
        ]);

        // Without the offered unit the cart holds two units: no lot, hence no
        // offer feeding itself a lot at every evaluation.
        self::assertSame(0.0, $coupon->exec());
    }

    public function testAProductOnSpecialOfferIsExcludedUnlessTheCouponAllowsIt(): void
    {
        $cartItems = [
            ['productId' => 7, 'quantity' => 1, 'price' => 10.0],
            ['productId' => 7, 'quantity' => 1, 'price' => 10.0, 'promo' => 1],
        ];
        $effects = [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
        ];

        self::assertSame(0.0, $this->coupon($this->cartWith($cartItems), $effects)->exec());
        self::assertSame(
            10.0,
            $this->coupon($this->cartWith($cartItems), $effects, availableOnSpecialOffers: true)->exec(),
        );
    }

    public function testAnEmptyTriggerListOffersNothing(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 10, 'price' => 10.0],
        ]);

        self::assertSame(0.0, $this->coupon($cart, [BuyXGetY::TRIGGER_IDS_FIELD => []])->exec());
    }

    public function testATargetProductDiscountsTheOfferedLineTheCouponMarked(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 2, 'price' => 10.0],
            ['productId' => 99, 'quantity' => 1, 'price' => 30.0, 'isOffered' => 1, 'offeredByCouponId' => 12],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
            BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_PRODUCT,
            BuyXGetY::TARGET_PRODUCT_ID_FIELD => 99,
        ], couponModelId: 12);

        self::assertSame(30.0, $coupon->exec());
    }

    public function testATargetProductDiscountsNothingWhileTheOfferedLineIsMissing(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 2, 'price' => 10.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
            BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_PRODUCT,
            BuyXGetY::TARGET_PRODUCT_ID_FIELD => 99,
        ], couponModelId: 12);

        self::assertSame(0.0, $coupon->exec());
    }

    public function testATargetProductIgnoresALineAnotherCouponOffers(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 2, 'price' => 10.0],
            ['productId' => 99, 'quantity' => 1, 'price' => 30.0, 'isOffered' => 1, 'offeredByCouponId' => 500],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
            BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_PRODUCT,
            BuyXGetY::TARGET_PRODUCT_ID_FIELD => 99,
        ], couponModelId: 12);

        self::assertSame(0.0, $coupon->exec());
    }

    public function testTheOfferedLineRequestAsksForOneUnitPerLot(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 5, 'price' => 10.0],
        ]);

        $facade = $this->facadeFor($cart);
        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
            BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_PRODUCT,
            BuyXGetY::TARGET_PRODUCT_ID_FIELD => 99,
        ], couponModelId: 12, facade: $facade);

        $requests = $coupon->getOfferedLineRequests($facade);

        self::assertCount(1, $requests);
        self::assertSame(99, $requests[0]->productId);
        self::assertSame(2, $requests[0]->quantity);
        self::assertSame(12, $requests[0]->couponId);
    }

    public function testNoOfferedLineIsRequestedWithoutACompleteLot(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 1, 'price' => 10.0],
        ]);

        $facade = $this->facadeFor($cart);
        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
            BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_PRODUCT,
            BuyXGetY::TARGET_PRODUCT_ID_FIELD => 99,
        ], couponModelId: 12, facade: $facade);

        self::assertSame([], $coupon->getOfferedLineRequests($facade));
    }

    public function testAnOfferTakenFromTheCartItselfRequestsNoOfferedLine(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 4, 'price' => 10.0],
        ]);

        $facade = $this->facadeFor($cart);
        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
        ], couponModelId: 12, facade: $facade);

        self::assertSame([], $coupon->getOfferedLineRequests($facade));
    }

    /**
     * An offer taken from the triggering units themselves must offer less than
     * the lot takes to form, or a lot of two would trigger itself entirely free.
     */
    public function testSavingAnOfferAsLargeAsItsLotIsRejectedWhenTakenFromTheTriggeringProducts(): void
    {
        $coupon = $this->coupon($this->cartWith([]), []);

        $this->expectException(\InvalidArgumentException::class);

        $coupon->getEffects([
            CouponAbstract::COUPON_DATASET_NAME => json_encode([
                BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_PRODUCT,
                BuyXGetY::TRIGGER_IDS_FIELD => [7],
                BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
                BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_SAME,
                BuyXGetY::OFFERED_QUANTITY_FIELD => 2,
                BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_FREE,
                BuyXGetY::DISCOUNT_VALUE_FIELD => 0.0,
            ], \JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * A distinct offered product is bought on top of the lot: offering as many
     * of it as the lot holds is a legitimate "buy 2, get 2 of Y".
     */
    public function testSavingAnOfferAsLargeAsItsLotIsAcceptedForADistinctOfferedProduct(): void
    {
        $coupon = $this->coupon($this->cartWith([]), []);

        $effects = $coupon->getEffects([
            CouponAbstract::COUPON_DATASET_NAME => json_encode([
                BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_PRODUCT,
                BuyXGetY::TRIGGER_IDS_FIELD => [7],
                BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
                BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_PRODUCT,
                BuyXGetY::TARGET_PRODUCT_ID_FIELD => 99,
                BuyXGetY::OFFERED_QUANTITY_FIELD => 2,
                BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_FREE,
                BuyXGetY::DISCOUNT_VALUE_FIELD => 0.0,
            ], \JSON_THROW_ON_ERROR),
        ]);

        self::assertSame(2, $effects[BuyXGetY::OFFERED_QUANTITY_FIELD]);
    }

    /**
     * Stored effects predating the save-time rule must not make a lot free:
     * the evaluation clamps the offer below the lot instead of granting it.
     */
    public function testAStoredOfferAsLargeAsItsLotIsClampedBelowTheLot(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 4, 'price' => 10.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
            BuyXGetY::OFFERED_QUANTITY_FIELD => 5,
        ]);

        // Two lots of two, the offer clamped to one unit per lot: never 40.0.
        self::assertSame(20.0, $coupon->exec());
    }

    public function testAStoredOfferOnALotOfOneOffersNothingOutsideProductMode(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 3, 'price' => 10.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 1,
            BuyXGetY::OFFERED_QUANTITY_FIELD => 1,
        ]);

        // A lot of one offering one unit of itself would make the whole cart free.
        self::assertSame(0.0, $coupon->exec());
    }

    public function testAStoredPercentageAboveOneHundredNeverDiscountsMoreThanTheOfferedUnitsCost(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 2, 'price' => 40.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
            BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_PERCENTAGE,
            BuyXGetY::DISCOUNT_VALUE_FIELD => 150.0,
        ]);

        // One unit offered at 40.0: the discount is capped there, never 60.0.
        self::assertSame(40.0, $coupon->exec());
    }

    public function testAStoredNegativePercentageDiscountsNothing(): void
    {
        $cart = $this->cartWith([
            ['productId' => 7, 'quantity' => 2, 'price' => 40.0],
        ]);

        $coupon = $this->coupon($cart, [
            BuyXGetY::TRIGGER_IDS_FIELD => [7],
            BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
            BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_PERCENTAGE,
            BuyXGetY::DISCOUNT_VALUE_FIELD => -25.0,
        ]);

        self::assertSame(0.0, $coupon->exec());
    }

    /**
     * @param array<string, mixed> $effects
     */
    private function coupon(
        Cart $cart,
        array $effects,
        bool $availableOnSpecialOffers = false,
        ?int $couponModelId = null,
        ?FacadeInterface $facade = null,
    ): BuyXGetY {
        $facade ??= $this->facadeFor($cart);

        $coupon = new BuyXGetY($facade);
        $coupon->set(
            $facade,
            'BXGY',
            'Buy 3 get 1',
            '',
            '',
            $effects,
            true,
            false,
            $availableOnSpecialOffers,
            true,
            -1,
            new \DateTime('+1 month'),
            [],
            [],
            false,
        );

        return $coupon->setCouponModelId($couponModelId);
    }

    private function facadeFor(Cart $cart): FacadeInterface
    {
        $translator = $this->createMock(Translator::class);
        $translator->method('trans')->willReturnArgument(0);

        $facade = $this->createMock(FacadeInterface::class);
        $facade->method('getTranslator')->willReturn($translator);
        $facade->method('getConditionEvaluator')->willReturn(new ConditionEvaluator());
        $facade->method('getCart')->willReturn($cart);
        $facade->method('getDeliveryCountry')->willReturn(new Country());

        return $facade;
    }

    /**
     * @param list<array{productId: int, quantity: int, price: float, promo?: int, isOffered?: int, offeredByCouponId?: int}> $lines
     */
    private function cartWith(array $lines): Cart
    {
        $cartItems = [];

        foreach ($lines as $line) {
            $cartItem = $this->createMock(CartItem::class);
            $cartItem->method('getProductId')->willReturn($line['productId']);
            $cartItem->method('getQuantity')->willReturn((float) $line['quantity']);
            $cartItem->method('getRealTaxedPrice')->willReturn($line['price']);
            $cartItem->method('getPromo')->willReturn($line['promo'] ?? 0);
            $cartItem->method('getIsOffered')->willReturn($line['isOffered'] ?? 0);
            $cartItem->method('getOfferedByCouponId')->willReturn($line['offeredByCouponId'] ?? null);

            $cartItems[] = $cartItem;
        }

        $cart = $this->createMock(Cart::class);
        // BuyXGetY only iterates getCartItems() with foreach, so a plain
        // iterator keeps the double clear of Propel's collection machinery.
        $cart->method('getCartItems')->willReturnCallback(
            static fn (): \ArrayIterator => new \ArrayIterator($cartItems),
        );

        return $cart;
    }
}
