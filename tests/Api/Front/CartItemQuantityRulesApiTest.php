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

namespace Thelia\Tests\Api\Front;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Domain\Catalog\Quantity\QuantityRule;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\ApiTestCase;

/**
 * The quantity a front API caller writes on a cart line is judged by the rules of its
 * combination: refused with the valid quantities when it is off the step or below the
 * minimum, taken with its decimals when the combination is sold by measure. Zero and
 * less stay refused whatever the rules.
 */
final class CartItemQuantityRulesApiTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{QuantityRule, float|int, int}>
     */
    public static function additions(): iterable
    {
        yield 'seven, sold by six' => [new QuantityRule(minQuantity: 2.0, step: 6.0), 7, 422];
        yield 'twelve, sold by six' => [new QuantityRule(minQuantity: 2.0, step: 6.0), 12, 201];
        yield 'a kilo and a quarter, to the hundredth' => [new QuantityRule(minQuantity: 0.25, step: 0.25, decimals: 2), 1.25, 201];
        yield 'one and a half, by the piece' => [new QuantityRule(), 1.5, 422];
        yield 'zero' => [new QuantityRule(), 0, 422];
        yield 'minus one' => [new QuantityRule(), -1, 422];
    }

    #[DataProvider('additions')]
    public function testAnAdditionIsJudgedByTheRulesOfItsCombination(QuantityRule $rule, float|int $quantity, int $status): void
    {
        [$customer, $cart, $saleElements] = $this->cartAndCombination($rule);

        $response = $this->jsonRequest('POST', '/api/front/cart_items', [
            'cart' => '/api/front/carts/'.$cart->getId(),
            'productSaleElements' => '/api/front/product_sale_elements/'.$saleElements->getId(),
            'quantity' => $quantity,
        ], $this->authenticateAsCustomer($customer));

        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());

        if (201 === $status) {
            self::assertSame([(float) $quantity], $this->quantitiesOf($cart));

            return;
        }

        self::assertSame([], $this->quantitiesOf($cart), 'A refused addition adds nothing.');
    }

    public function testARefusalOffTheStepGivesTheValidQuantities(): void
    {
        [$customer, $cart, $saleElements] = $this->cartAndCombination(new QuantityRule(minQuantity: 2.0, step: 6.0));

        $response = $this->jsonRequest('POST', '/api/front/cart_items', [
            'cart' => '/api/front/carts/'.$cart->getId(),
            'productSaleElements' => '/api/front/product_sale_elements/'.$saleElements->getId(),
            'quantity' => 7,
        ], $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('Sold by 6: 6, 12, 18', (string) $response->getContent());
    }

    /**
     * Rules stored around their validation decide no quantity: the caller is refused,
     * not shown a server error.
     */
    public function testAnAdditionToRulesThatCannotDecideAQuantityIsRefused(): void
    {
        [$customer, $cart, $saleElements] = $this->cartAndCombination(new QuantityRule());
        $saleElements->setMinQuantity('0.000000')->save($this->getPropelConnection());

        $response = $this->jsonRequest('POST', '/api/front/cart_items', [
            'cart' => '/api/front/carts/'.$cart->getId(),
            'productSaleElements' => '/api/front/product_sale_elements/'.$saleElements->getId(),
            'quantity' => 1,
        ], $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([], $this->quantitiesOf($cart));
    }

    /**
     * @return iterable<string, array{QuantityRule, float|int, float}>
     */
    public static function changes(): iterable
    {
        yield 'seven, sold by six' => [new QuantityRule(minQuantity: 2.0, step: 6.0), 7, 6.0];
        yield 'twelve, sold by six' => [new QuantityRule(minQuantity: 2.0, step: 6.0), 12, 12.0];
        yield 'a kilo and a quarter, to the hundredth' => [new QuantityRule(minQuantity: 0.25, step: 0.25, decimals: 2), 1.25, 1.25];
        yield 'one and a half, by the piece' => [new QuantityRule(), 1.5, 6.0];
        yield 'zero' => [new QuantityRule(), 0, 6.0];
        yield 'minus one' => [new QuantityRule(), -1, 6.0];
    }

    /**
     * The line holds 6 before the change: a refused change leaves it there.
     */
    #[DataProvider('changes')]
    public function testAChangeIsJudgedByTheRulesOfItsCombination(QuantityRule $rule, float|int $quantity, float $kept): void
    {
        [$customer, $cart, $saleElements] = $this->cartAndCombination($rule);
        $line = $this->createFixtureFactory()->cartItem($cart, $saleElements->getProduct(), $saleElements, ['quantity' => 6]);

        $response = $this->jsonRequest('PUT', '/api/front/cart_items/'.$line->getId(), ['quantity' => $quantity], $this->authenticateAsCustomer($customer));

        self::assertSame(abs($kept - (float) $quantity) < 0.000001 ? 200 : 422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([$kept], $this->quantitiesOf($cart));
    }

    /**
     * @return array{0: Customer, 1: Cart, 2: ProductSaleElements}
     */
    private function cartAndCombination(QuantityRule $rule): array
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['baseQuantity' => 100]);
        $saleElements = $product->getProductSaleElementss()->getFirst();
        $rule->applyTo($saleElements);
        $saleElements->save($this->getPropelConnection());

        return [$customer, $cart, $saleElements];
    }

    /**
     * @return list<float>
     */
    private function quantitiesOf(Cart $cart): array
    {
        CartItemTableMap::clearInstancePool();

        $quantities = [];

        foreach (CartItemQuery::create()->filterByCartId($cart->getId())->find($this->getPropelConnection()) as $line) {
            $quantities[] = round((float) $line->getQuantity(), 6);
        }

        return $quantities;
    }
}
