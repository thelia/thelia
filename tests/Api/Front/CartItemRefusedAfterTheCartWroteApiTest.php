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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Exception\InvalidCartException;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Test\ApiTestCase;

/**
 * The cart writes a line at priority 128, before every listener has had its say: a
 * module that refuses afterwards answers a 422, and the cart must then be the one
 * the caller had. The write is rolled back for real, which a test wrapped in its own
 * transaction cannot see (Propel nests transactions without savepoints), so this
 * one commits its fixtures and removes them itself.
 */
final class CartItemRefusedAfterTheCartWroteApiTest extends ApiTestCase
{
    private const int BEFORE_THE_CART = 256;

    private const int AFTER_THE_CART = 64;

    protected bool $useTransaction = false;

    /** @var list<array{string, callable}> */
    private array $registeredListeners = [];

    /** @var list<int> */
    private array $customerIds = [];

    /** @var list<int> */
    private array $productIds = [];

    /** @var list<int> */
    private array $categoryIds = [];

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }

        $this->registeredListeners = [];

        // The carts and their lines follow the customer, the sale elements and their
        // prices follow the product (ON DELETE CASCADE).
        $connection = $this->getPropelConnection();
        CustomerQuery::create()->filterById($this->customerIds)->delete($connection);
        ProductQuery::create()->filterById($this->productIds)->delete($connection);
        CategoryQuery::create()->filterById($this->categoryIds)->delete($connection);

        parent::tearDown();
    }

    public function testAnAdditionRefusedAfterTheCartWroteTheLineAddsNothing(): void
    {
        [$token, $cart, $product] = $this->buyerWithACart();
        $this->refuseAfterTheCart(TheliaEvents::CART_ADDITEM);

        $response = $this->jsonRequest('POST', '/api/front/cart_items', [
            'cart' => '/api/front/carts/'.$cart->getId(),
            'productSaleElements' => '/api/front/product_sale_elements/'.$product->getProductSaleElementss()->getFirst()->getId(),
            'quantity' => 2,
        ], $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        CartItemTableMap::clearInstancePool();
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
    }

    public function testAQuantityChangeRefusedAfterTheCartWroteItLeavesTheLineAsItWas(): void
    {
        [$token, $cart, $product] = $this->buyerWithACart();
        $cartItem = $this->createFixtureFactory()->cartItem($cart, $product);
        $this->refuseAfterTheCart(TheliaEvents::CART_UPDATEITEM);

        $response = $this->jsonRequest('PUT', '/api/front/cart_items/'.$cartItem->getId(), ['quantity' => 5], $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        CartItemTableMap::clearInstancePool();
        self::assertSame(1.0, CartItemQuery::create()->findPk($cartItem->getId(), $this->getPropelConnection())?->getQuantity());
    }

    public function testARemovalRefusedAfterTheCartDeletedItKeepsTheLine(): void
    {
        [$token, $cart, $product] = $this->buyerWithACart();
        $cartItem = $this->createFixtureFactory()->cartItem($cart, $product);
        $this->refuseAfterTheCart(TheliaEvents::CART_DELETEITEM);

        $response = $this->jsonRequest('DELETE', '/api/front/cart_items/'.$cartItem->getId(), token: $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        CartItemTableMap::clearInstancePool();
        self::assertSame(1, CartItemQuery::create()->filterById($cartItem->getId())->count($this->getPropelConnection()));
    }

    /**
     * A module ahead of the cart may take less than was asked (rounding, a limit of
     * its own): the caller asked for a quantity, and gets it whole or not at all.
     */
    public function testAnAdditionTheCartDidNotTakeWholeAddsNothing(): void
    {
        [$token, $cart, $product] = $this->buyerWithACart();
        $this->listen(TheliaEvents::CART_ADDITEM, static function (CartEvent $event): void {
            $event->setQuantity((int) $event->getQuantity() - 1);
        }, self::BEFORE_THE_CART);

        $response = $this->jsonRequest('POST', '/api/front/cart_items', [
            'cart' => '/api/front/carts/'.$cart->getId(),
            'productSaleElements' => '/api/front/product_sale_elements/'.$product->getProductSaleElementss()->getFirst()->getId(),
            'quantity' => 5,
        ], $token);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('The cart did not take the whole quantity.', (string) $response->getContent());
        CartItemTableMap::clearInstancePool();
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
    }

    /**
     * @return array{0: string, 1: Cart, 2: Product}
     */
    private function buyerWithACart(): array
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $this->customerIds[] = (int) $customer->getId();
        $category = $factory->category();
        $this->categoryIds[] = (int) $category->getId();
        $product = $factory->product($category, $factory->taxRule(), $factory->currency(), ['baseQuantity' => 100]);
        $this->productIds[] = (int) $product->getId();

        return [$this->authenticateAsCustomer($customer), $factory->cart($customer), $product];
    }

    private function refuseAfterTheCart(string $eventName): void
    {
        $this->listen($eventName, static function (): void {
            throw new InvalidCartException('Refused once the cart wrote the line.');
        }, self::AFTER_THE_CART);
    }

    private function listen(string $eventName, callable $listener, int $priority): void
    {
        $this->dispatcher()->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }
}
