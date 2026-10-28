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
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The contract of POST /api/front/cart_items does not move with the rule the cart now holds
 * itself: a sale element of a category closed to the group of the caller is not found (the
 * catalog hides it, so the IRI of the body leads nowhere: 400, as for any sale element the
 * caller cannot see) and the cart is left as it was, one of an open category is added, the
 * default group adds both. The refusal of the cart itself (422) is the one of the domain, for
 * the paths that never read the catalog first.
 */
final class CartItemClosedCategoryApiTest extends ApiTestCase
{
    private FixtureFactory $factory;

    private CustomerGroup $pro;

    private Product $table;

    private Product $sofa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
        $diningRoom = $this->factory->category();
        $sofas = $this->factory->category();
        $currency = $this->factory->currency();
        $taxRule = $this->factory->taxRule();
        $this->table = $this->factory->product($diningRoom, $taxRule, $currency, ['baseQuantity' => 100]);
        $this->sofa = $this->factory->product($sofas, $taxRule, $currency, ['baseQuantity' => 100]);
        $this->pro = $this->factory->customerGroup('pro', categoryIds: [(int) $sofas->getId()]);
        $this->getService('services_resetter')->reset();
    }

    protected function tearDown(): void
    {
        $this->getService('services_resetter')->reset();

        parent::tearDown();
    }

    public function testAProfessionalCannotAddAProductOfAClosedCategory(): void
    {
        [$token, $cart] = $this->buyer($this->pro);

        $response = $this->add($token, $cart, $this->table);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testAProfessionalAddsAProductOfTheOpenCategory(): void
    {
        [$token, $cart] = $this->buyer($this->pro);

        $response = $this->add($token, $cart, $this->sofa);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(1, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testTheDefaultGroupAddsBoth(): void
    {
        [$token, $cart] = $this->buyer(null);

        self::assertSame(Response::HTTP_CREATED, $this->add($token, $cart, $this->table)->getStatusCode());
        self::assertSame(Response::HTTP_CREATED, $this->add($token, $cart, $this->sofa)->getStatusCode());
        self::assertSame(2, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testTheQuantityOfALineOfAClosedCategoryCannotBeRaisedButItCanBeReducedAndRemoved(): void
    {
        // The line was added when the category was open, or by the visitor who then signed in.
        [$token, $cart] = $this->buyer($this->pro);
        $line = $this->factory->cartItem($cart, $this->table, null, ['quantity' => 3.0]);

        $raise = $this->jsonRequest('PUT', '/api/front/cart_items/'.$line->getId(), ['quantity' => 5], $token);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $raise->getStatusCode(), (string) $raise->getContent());
        self::assertSame(3.0, $this->quantityOf($line));

        $reduce = $this->jsonRequest('PUT', '/api/front/cart_items/'.$line->getId(), ['quantity' => 2], $token);

        self::assertSame(Response::HTTP_OK, $reduce->getStatusCode(), (string) $reduce->getContent());
        self::assertSame(2.0, $this->quantityOf($line));

        $remove = $this->jsonRequest('DELETE', '/api/front/cart_items/'.$line->getId(), token: $token);

        self::assertSame(Response::HTTP_NO_CONTENT, $remove->getStatusCode(), (string) $remove->getContent());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    private function quantityOf(CartItem $line): float
    {
        CartItemTableMap::clearInstancePool();

        return (float) CartItemQuery::create()->findPk($line->getId())?->getQuantity();
    }

    /**
     * @return array{string, Cart}
     */
    private function buyer(?CustomerGroup $group): array
    {
        $customer = $this->factory->customer(
            $this->factory->customerTitle(),
            ['password' => 'password'] + (null === $group ? [] : ['customerGroup' => $group]),
        );

        return [$this->authenticateAsCustomer($customer), $this->factory->cart($customer)];
    }

    private function add(string $token, Cart $cart, Product $product): Response
    {
        return $this->jsonRequest('POST', '/api/front/cart_items', [
            'cart' => '/api/front/carts/'.$cart->getId(),
            'productSaleElements' => '/api/front/product_sale_elements/'.$product->getProductSaleElementss()->getFirst()->getId(),
            'quantity' => 1,
        ], $token);
    }
}
