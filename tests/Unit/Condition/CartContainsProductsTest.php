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

namespace Thelia\Tests\Unit\Condition;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Condition\Exception\InvalidConditionOperatorException;
use Thelia\Condition\Exception\InvalidConditionValueException;
use Thelia\Condition\Implementation\CartContainsProducts;
use Thelia\Condition\Operators;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Product;
use Thelia\Tests\Support\Log\SilencedTlog;

/**
 * A cart content condition takes a non-empty list of products with the in or out
 * operator. The exceptions rejecting a bad form log an error, so the logger reads a
 * configuration sending it nowhere instead of the database.
 */
final class CartContainsProductsTest extends FacadeBackedTestCase
{
    use SilencedTlog;

    protected function setUp(): void
    {
        $this->silenceTlog();
    }

    protected function tearDown(): void
    {
        $this->restoreTlog();
    }

    public function testServiceIdIsStable(): void
    {
        $condition = new CartContainsProducts($this->makeFacade());

        self::assertSame('thelia.condition.cart_contains_products', $condition->getServiceId());
    }

    public function testIsMatchingReturnsTrueWhenCartContainsOneOfTheListedProducts(): void
    {
        $facade = $this->makeFacade();
        $facade->method('getCart')->willReturn($this->cartWithProductIds([5, 9]));

        $condition = (new CartContainsProducts($facade))->setValidatorsFromForm(
            [CartContainsProducts::PRODUCTS_LIST => Operators::IN],
            [CartContainsProducts::PRODUCTS_LIST => [9, 42]],
        );

        self::assertTrue($condition->isMatching());
    }

    public function testIsMatchingReturnsFalseWhenCartMissesAllListedProducts(): void
    {
        $facade = $this->makeFacade();
        $facade->method('getCart')->willReturn($this->cartWithProductIds([1, 2]));

        $condition = (new CartContainsProducts($facade))->setValidatorsFromForm(
            [CartContainsProducts::PRODUCTS_LIST => Operators::IN],
            [CartContainsProducts::PRODUCTS_LIST => [42]],
        );

        self::assertFalse($condition->isMatching());
    }

    public function testOutOperatorInvertsTheMatch(): void
    {
        $facade = $this->makeFacade();
        $facade->method('getCart')->willReturn($this->cartWithProductIds([1, 2]));

        $condition = (new CartContainsProducts($facade))->setValidatorsFromForm(
            [CartContainsProducts::PRODUCTS_LIST => Operators::OUT],
            [CartContainsProducts::PRODUCTS_LIST => [42]],
        );

        self::assertTrue($condition->isMatching());
    }

    public function testSetValidatorsFromFormWrapsScalarValueInArray(): void
    {
        $facade = $this->makeFacade();
        $facade->method('getCart')->willReturn($this->cartWithProductIds([7]));

        $condition = (new CartContainsProducts($facade))->setValidatorsFromForm(
            [CartContainsProducts::PRODUCTS_LIST => Operators::IN],
            [CartContainsProducts::PRODUCTS_LIST => 7],
        );

        self::assertTrue($condition->isMatching());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function comparisonOperators(): iterable
    {
        yield 'equal to' => [Operators::EQUAL];
        yield 'greater than' => [Operators::SUPERIOR];
    }

    #[DataProvider('comparisonOperators')]
    public function testAnOperatorOtherThanInOrOutIsRejected(string $operator): void
    {
        $this->expectException(InvalidConditionOperatorException::class);

        (new CartContainsProducts($this->makeFacade()))->setValidatorsFromForm(
            [CartContainsProducts::PRODUCTS_LIST => $operator],
            [CartContainsProducts::PRODUCTS_LIST => [4]],
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function formsWithoutASelection(): iterable
    {
        yield 'an empty list' => [[CartContainsProducts::PRODUCTS_LIST => []]];
        yield 'no list at all' => [[]];
        yield 'a list left blank' => [[CartContainsProducts::PRODUCTS_LIST => null]];
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('formsWithoutASelection')]
    public function testAFormWithoutASelectionIsRejected(array $values): void
    {
        $this->expectException(InvalidConditionValueException::class);
        $this->expectExceptionMessage('on parameter products');

        (new CartContainsProducts($this->makeFacade()))->setValidatorsFromForm(
            [CartContainsProducts::PRODUCTS_LIST => Operators::OUT],
            $values,
        );
    }

    /**
     * @param list<int> $productIds
     */
    private function cartWithProductIds(array $productIds): Cart
    {
        $items = [];
        foreach ($productIds as $id) {
            $product = $this->createMock(Product::class);
            $product->method('getId')->willReturn($id);

            $cartItem = $this->createMock(CartItem::class);
            $cartItem->method('getProduct')->willReturn($product);
            $items[] = $cartItem;
        }

        $cart = $this->createMock(Cart::class);
        // CartContainsProducts only iterates the return value of getCartItems()
        // with `foreach`, so returning a plain array keeps the mock free of
        // Propel ObjectCollection's hash-code machinery.
        $cart->method('getCartItems')->willReturn(new \ArrayIterator($items));

        return $cart;
    }
}
