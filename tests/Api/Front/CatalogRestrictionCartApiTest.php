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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CatalogRestriction;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * A sale element outside the assortment of the caller is refused by the cart itself, by the
 * path that never reads the catalog first (the addition by IRI of a sale element the caller
 * cannot see is a 400 before it, see CartItemClosedCategoryApiTest): the cart of a caller who
 * holds a line that went out of the assortment answers 422 when its quantity is raised, and the
 * product is not found for the same caller on its own item (404, never a 403).
 */
final class CatalogRestrictionCartApiTest extends ApiTestCase
{
    private FixtureFactory $factory;

    private CustomerGroup $buyers;

    private Product $allowed;

    private Product $outside;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $currency = $this->factory->currency();
        $taxRule = $this->factory->taxRule();
        $this->allowed = $this->factory->product($this->factory->category(), $taxRule, $currency, ['baseQuantity' => 100]);
        $this->outside = $this->factory->product($this->factory->category(), $taxRule, $currency, ['baseQuantity' => 100]);
        $this->buyers = $this->factory->customerGroup('buyers');
        (new CatalogRestriction())->setCustomerGroupId($this->buyers->getId())->setProductId($this->allowed->getId())->setMode('allow')->save($this->getPropelConnection());
        $this->getService('services_resetter')->reset();
    }

    protected function tearDown(): void
    {
        $this->getService('services_resetter')->reset();

        parent::tearDown();
    }

    public function testTheSaleElementOutsideTheAssortmentCannotBeAdded(): void
    {
        [$token, $cart] = $this->buyer();

        $response = $this->add($token, $cart, $this->outside);

        self::assertContains($response->getStatusCode(), [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY], (string) $response->getContent());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
        self::assertSame(Response::HTTP_NOT_FOUND, $this->jsonRequest('GET', '/api/front/products/'.$this->outside->getId(), token: $token)->getStatusCode());
    }

    public function testTheSaleElementOfTheAssortmentIsAdded(): void
    {
        [$token, $cart] = $this->buyer();

        $response = $this->add($token, $cart, $this->allowed);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(1, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testTheQuantityOfALineThatLeftTheAssortmentCannotBeRaised(): void
    {
        [$token, $cart] = $this->buyer();
        $line = $this->factory->cartItem($cart, $this->outside, null, ['quantity' => 3.0]);

        $response = $this->jsonRequest('PUT', '/api/front/cart_items/'.$line->getId(), ['quantity' => 5], $token);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * @return array{string, Cart}
     */
    private function buyer(): array
    {
        $customer = $this->factory->customer($this->factory->customerTitle(), ['password' => 'password', 'customerGroup' => $this->buyers]);

        return [$this->authenticateAsCustomer($customer), $this->factory->cart($customer)];
    }

    private function add(string $token, Cart $cart, Product $product): Response
    {
        return $this->jsonRequest('POST', '/api/front/cart_items', [
            'cart' => '/api/front/carts/'.$cart->getId(),
            'productSaleElements' => '/api/front/product_sale_elements/'.$product->getProductSaleElementss()->getFirst()->getId(),
            'quantity' => 2,
        ], $token);
    }
}
