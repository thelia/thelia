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

namespace Thelia\Tests\Unit\Domain\Promotion\Coupon\Type;

use PHPUnit\Framework\TestCase;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\Type\AbstractRemoveOnProducts;
use Thelia\Domain\Promotion\Coupon\Type\RemovePercentageOnProducts;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Country;
use Thelia\Model\Product;

/**
 * What every "on selected products" coupon reads from its stored effects: the
 * products it discounts and the category the back office picked them from.
 */
final class AbstractRemoveOnProductsTest extends TestCase
{
    public function testEverySelectedProductIsDiscountedAndNoOther(): void
    {
        $cart = $this->cartWith([
            ['productId' => 5, 'total' => 20.0],
            ['productId' => 6, 'total' => 50.0],
            ['productId' => 7, 'total' => 30.0],
        ]);

        $coupon = $this->couponSetWith([
            AbstractRemoveOnProducts::PRODUCTS_LIST => [5, 6],
            RemovePercentageOnProducts::PERCENTAGE => 10,
        ], $cart);

        self::assertEqualsWithDelta(7.0, $coupon->exec(), 0.0001);
    }

    public function testAProductOnSpecialOfferIsNotDiscountedUnlessTheCouponAllowsIt(): void
    {
        $cart = $this->cartWith([
            ['productId' => 5, 'total' => 20.0, 'promo' => 1],
            ['productId' => 6, 'total' => 50.0],
        ]);

        $coupon = $this->couponSetWith([
            AbstractRemoveOnProducts::PRODUCTS_LIST => [5, 6],
            RemovePercentageOnProducts::PERCENTAGE => 10,
        ], $cart);

        self::assertEqualsWithDelta(5.0, $coupon->exec(), 0.0001);
    }

    public function testAProductOnSpecialOfferIsDiscountedWhenTheCouponAllowsIt(): void
    {
        $cart = $this->cartWith([
            ['productId' => 5, 'total' => 20.0, 'promo' => 1],
            ['productId' => 6, 'total' => 50.0],
        ]);

        $coupon = $this->couponSetWith([
            AbstractRemoveOnProducts::PRODUCTS_LIST => [5, 6],
            RemovePercentageOnProducts::PERCENTAGE => 10,
        ], $cart, availableOnSpecialOffers: true);

        self::assertEqualsWithDelta(7.0, $coupon->exec(), 0.0001);
    }

    public function testTheFormShowsTheStoredCategoryAsANumber(): void
    {
        self::assertSame(3, $this->formParametersAfterReading([
            AbstractRemoveOnProducts::CATEGORY_ID => '3',
            RemovePercentageOnProducts::PERCENTAGE => 10,
        ])['category_id_value']);
    }

    public function testTheFormShowsNoCategoryWhenNoneIsStored(): void
    {
        self::assertSame(0, $this->formParametersAfterReading([
            RemovePercentageOnProducts::PERCENTAGE => 10,
        ])['category_id_value']);
    }

    /**
     * @param array<string, mixed> $effects
     */
    private function couponSetWith(
        array $effects,
        ?Cart $cart = null,
        ?ParserInterface $parser = null,
        bool $availableOnSpecialOffers = false,
    ): RemovePercentageOnProducts {
        $translator = $this->createStub(Translator::class);

        $facade = $this->createStub(FacadeInterface::class);
        $facade->method('getTranslator')->willReturn($translator);
        $facade->method('getConditionEvaluator')->willReturn(new ConditionEvaluator());
        $facade->method('getCart')->willReturn($cart);
        $facade->method('getDeliveryCountry')->willReturn(new Country());
        if (null !== $parser) {
            $facade->method('getParser')->willReturn($parser);
        }

        return (new RemovePercentageOnProducts($facade))->set(
            $facade,
            'SELECTION',
            'Selection',
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
    }

    /**
     * @param array<string, mixed> $effects
     *
     * @return array<string, mixed>
     */
    private function formParametersAfterReading(array $effects): array
    {
        $rendered = [];
        $parser = $this->createStub(ParserInterface::class);
        $parser->method('render')->willReturnCallback(
            static function (string $template, array $parameters) use (&$rendered): string {
                $rendered = $parameters;

                return '';
            },
        );

        $this->couponSetWith($effects, parser: $parser)->drawBackOfficeInputs();

        return $rendered;
    }

    /**
     * @param list<array{productId: int, total: float, promo?: int}> $lines
     */
    private function cartWith(array $lines): Cart
    {
        $cartItems = array_map(function (array $line): CartItem {
            $product = $this->createStub(Product::class);
            $product->method('getId')->willReturn($line['productId']);

            $cartItem = $this->createStub(CartItem::class);
            $cartItem->method('getProduct')->willReturn($product);
            $cartItem->method('getPromo')->willReturn($line['promo'] ?? 0);
            $cartItem->method('getTotalRealTaxedPrice')->willReturn($line['total']);

            return $cartItem;
        }, $lines);

        $cart = $this->createStub(Cart::class);
        $cart->method('getCartItems')->willReturnCallback(
            static fn (): \ArrayIterator => new \ArrayIterator($cartItems),
        );

        return $cart;
    }
}
