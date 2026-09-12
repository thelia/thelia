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

use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * A line a promotion offered belongs to the promotion: the customer sees it,
 * and neither changes nor removes it. The front cart endpoints write through
 * the generic Propel processors, which dispatch no cart event, so the lock has
 * to sit on the operation itself.
 */
final class CartItemOfferedLineApiTest extends ApiTestCase
{
    public function testTheOwnerCannotChangeTheQuantityOfAnOfferedLine(): void
    {
        [$customer, $offeredLine] = $this->cartWithAnOfferedLine();
        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest(
            'PUT',
            '/api/front/cart_items/'.$offeredLine->getId(),
            ['quantity' => 99],
            token: $token,
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(1.0, $this->reloadQuantity($offeredLine->getId()));
    }

    public function testTheOwnerCannotDeleteAnOfferedLine(): void
    {
        [$customer, $offeredLine] = $this->cartWithAnOfferedLine();
        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest(
            'DELETE',
            '/api/front/cart_items/'.$offeredLine->getId(),
            token: $token,
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(
            1,
            CartItemQuery::create()->filterById($offeredLine->getId())->count($this->getPropelConnection()),
        );
    }

    public function testAnOfferedLineIsStillReadable(): void
    {
        [$customer, $offeredLine] = $this->cartWithAnOfferedLine();
        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/cart_items/'.$offeredLine->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);

        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertTrue($data['isOffered'], 'The front needs to know the line is offered to render it as such.');
    }

    public function testALineTheCustomerAddedIsStillTheirsToChange(): void
    {
        [$customer, , $ownLine] = $this->cartWithAnOfferedLine();
        $token = $this->authenticateAsCustomer($customer);

        // A front PUT replaces the line, so it carries the sale element too.
        $response = $this->jsonRequest(
            'PUT',
            '/api/front/cart_items/'.$ownLine->getId(),
            [
                'quantity' => 3,
                'productSaleElements' => '/api/front/product_sale_elements/'.$ownLine->getProductSaleElementsId(),
            ],
            token: $token,
        );

        self::assertJsonResponseSuccessful($response);
        self::assertSame(3.0, $this->reloadQuantity($ownLine->getId()));
    }

    public function testALineTheCustomerAddedIsNotFlaggedAsOffered(): void
    {
        [$customer, , $ownLine] = $this->cartWithAnOfferedLine();
        $token = $this->authenticateAsCustomer($customer);

        $response = $this->jsonRequest('GET', '/api/front/cart_items/'.$ownLine->getId(), token: $token);

        self::assertJsonResponseSuccessful($response);

        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertFalse($data['isOffered']);
    }

    /**
     * @return array{0: Customer, 1: CartItem, 2: CartItem}
     */
    private function cartWithAnOfferedLine(?FixtureFactory $factory = null): array
    {
        $factory ??= $this->createFixtureFactory();

        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $boughtLine = $factory->cartItem($cart, $factory->product($category, $taxRule, $currency));

        $coupon = $factory->coupon(['code' => null, 'triggerMode' => 'automatic']);
        $offeredLine = $factory->cartItem(
            $cart,
            $factory->product($category, $taxRule, $currency),
            overrides: ['isOffered' => 1, 'offeredByCouponId' => $coupon->getId()],
        );

        return [$customer, $offeredLine, $boughtLine];
    }

    private function reloadQuantity(int $cartItemId): ?float
    {
        CartItemTableMap::clearInstancePool();

        return CartItemQuery::create()
            ->findPk($cartItemId, $this->getPropelConnection())
            ?->getQuantity();
    }
}
