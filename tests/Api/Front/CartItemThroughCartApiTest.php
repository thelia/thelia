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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Exception\InvalidCartException;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\Sale;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * A cart line written through the front API goes through the cart, the way the theme
 * writes it: CART_ADDITEM, CART_UPDATEITEM and CART_DELETEITEM are where the shop
 * prices the line, checks the stock, and where a module refuses a quantity or a
 * product. A line written straight into the table skips every one of them.
 */
final class CartItemThroughCartApiTest extends ApiTestCase
{
    /** @var list<array{string, callable}> */
    private array $registeredListeners = [];

    private ?string $stockCheckBefore = null;

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }

        $this->registeredListeners = [];

        // The configuration lives in a cache shared beyond the transaction the test
        // rolls back: the value a test wrote is put back before the rollback, or the
        // next test file reads it.
        if (null !== $this->stockCheckBefore) {
            ConfigQuery::write('check-available-stock', $this->stockCheckBefore);
            $this->stockCheckBefore = null;
        }

        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheOwnerChangesTheQuantityOfALine(): void
    {
        [$customer, , $cartItem] = $this->cartLine(stock: 100);

        $response = $this->jsonRequest('PUT', '/api/front/cart_items/'.$cartItem->getId(), ['quantity' => 3], $this->authenticateAsCustomer($customer));

        self::assertJsonResponseSuccessful($response);
        self::assertSame(3.0, $this->reloadQuantity($cartItem->getId()));
    }

    public function testAQuantityChangeTheCartRefusesLeavesTheLineAsItWas(): void
    {
        [$customer, , $cartItem] = $this->cartLine(stock: 100);
        $this->listen(TheliaEvents::CART_UPDATEITEM, static function (): void {
            throw new InvalidCartException('Sold by 6 only.');
        });

        $response = $this->jsonRequest('PUT', '/api/front/cart_items/'.$cartItem->getId(), ['quantity' => 5], $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(1.0, $this->reloadQuantity($cartItem->getId()));
    }

    public function testAQuantityBeyondTheStockIsRefused(): void
    {
        $this->checkStock(true);
        [$customer, , $cartItem] = $this->cartLine(stock: 10);

        $response = $this->jsonRequest('PUT', '/api/front/cart_items/'.$cartItem->getId(), ['quantity' => 100000], $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(1.0, $this->reloadQuantity($cartItem->getId()));
    }

    public function testALineDoesNotSwitchToAnotherSaleElement(): void
    {
        $factory = $this->createFixtureFactory();
        [$customer, , $cartItem] = $this->cartLine(stock: 100);
        $other = $this->product($factory, stock: 100)->getProductSaleElementss()->getFirst();

        $response = $this->jsonRequest('PUT', '/api/front/cart_items/'.$cartItem->getId(), [
            'quantity' => 2,
            'productSaleElements' => '/api/front/product_sale_elements/'.$other->getId(),
        ], $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode());
        CartItemTableMap::clearInstancePool();
        $line = CartItemQuery::create()->findPk($cartItem->getId(), $this->getPropelConnection());
        self::assertSame($cartItem->getProductSaleElementsId(), $line?->getProductSaleElementsId());
        self::assertSame(1.0, $line?->getQuantity());
    }

    public function testTheOwnerAddsALineToTheirCart(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $product = $this->product($factory, stock: 100);
        $additions = 0;
        $this->listen(TheliaEvents::CART_ADDITEM, static function () use (&$additions): void {
            ++$additions;
        });

        // Priced by the shop, never by the caller: whatever price the body carries.
        $response = $this->jsonRequest('POST', '/api/front/cart_items', $this->addition($cart, $product, 2) + [
            'price' => 0.01,
            'promoPrice' => 0.01,
            'promo' => 1,
        ], $this->authenticateAsCustomer($customer));

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(1, $additions);
        $line = $this->onlyLineOf($cart);
        self::assertSame(2.0, (float) $line->getQuantity());
        self::assertSame($product->getId(), $line->getProductId());
        self::assertEqualsWithDelta(10.0, (float) $line->getPrice(), 0.0001);
        self::assertEqualsWithDelta(10.0, (float) $line->getPromoPrice(), 0.0001);
        self::assertSame(0, (int) $line->getPromo());
    }

    public function testAnAdditionTheCartRefusesAddsNothing(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $product = $this->product($factory, stock: 100);
        $this->listen(TheliaEvents::CART_ADDITEM, static function (): void {
            throw new InvalidCartException('Not open to this customer.');
        });

        $response = $this->jsonRequest('POST', '/api/front/cart_items', $this->addition($cart, $product, 2), $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
    }

    public function testNobodyAddsALineToSomebodyElsesCart(): void
    {
        $factory = $this->createFixtureFactory();
        $owner = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($owner);
        $product = $this->product($factory, stock: 100);
        $intruder = $factory->customer($factory->customerTitle(), ['password' => 'password']);

        $response = $this->jsonRequest('POST', '/api/front/cart_items', $this->addition($cart, $product, 2), $this->authenticateAsCustomer($intruder));

        self::assertSame(404, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
    }

    /**
     * @return iterable<string, array{callable(ProductSaleElements): mixed}>
     */
    public static function waysToNameASaleElement(): iterable
    {
        yield 'front IRI' => [static fn (ProductSaleElements $saleElements): string => '/api/front/product_sale_elements/'.$saleElements->getId()];
        yield 'admin IRI' => [static fn (ProductSaleElements $saleElements): string => '/api/admin/product_sale_elements/'.$saleElements->getId()];
        yield 'plain identifier' => [static fn (ProductSaleElements $saleElements): int => (int) $saleElements->getId()];
    }

    /**
     * The catalogue a buyer can order from is the one the shop shows: a product
     * taken offline is out of reach, however its sale element is named.
     *
     * @param callable(ProductSaleElements): mixed $name
     */
    #[DataProvider('waysToNameASaleElement')]
    public function testNobodyAddsAProductTheShopTookOffline(callable $name): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $offline = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['visible' => 0, 'baseQuantity' => 100]);

        $response = $this->jsonRequest('POST', '/api/front/cart_items', [
            'cart' => '/api/front/carts/'.$cart->getId(),
            'productSaleElements' => $name($offline->getProductSaleElementss()->getFirst()),
            'quantity' => 1,
        ], $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
    }

    public function testNobodyAddsASaleElementTheShopTookOffline(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $product = $this->product($factory, stock: 100);
        $saleElements = $product->getProductSaleElementss()->getFirst();
        $saleElements->setVisible(0)->save($this->getPropelConnection());

        $response = $this->jsonRequest('POST', '/api/front/cart_items', $this->addition($cart, $product, 1), $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
    }

    /**
     * A private drop hides its products from everybody it does not name, and
     * answers the way a product that does not exist answers.
     */
    public function testAProductOfAPrivateDropIsOutOfReachForACustomerItDoesNotName(): void
    {
        $factory = $this->createFixtureFactory();
        $invited = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $outsider = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($outsider);
        $product = $this->product($factory, stock: 100);
        $this->privateDropOf($factory, $product, $invited);

        $response = $this->jsonRequest('POST', '/api/front/cart_items', $this->addition($cart, $product, 1, admin: true), $this->authenticateAsCustomer($outsider));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
    }

    public function testTheCustomerAPrivateDropNamesAddsItsProduct(): void
    {
        $factory = $this->createFixtureFactory();
        $invited = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($invited);
        $product = $this->product($factory, stock: 100);
        $this->privateDropOf($factory, $product, $invited);

        $response = $this->jsonRequest('POST', '/api/front/cart_items', $this->addition($cart, $product, 1), $this->authenticateAsCustomer($invited));

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testAnAdditionBeyondTheStockIsRefused(): void
    {
        $this->checkStock(true);
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $product = $this->product($factory, stock: 10);

        $response = $this->jsonRequest('POST', '/api/front/cart_items', $this->addition($cart, $product, 11), $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
    }

    /**
     * What the cart already holds counts: the cart would otherwise keep its quantity
     * without a word and the caller would be told the addition went through.
     */
    public function testAnAdditionThatTheLineInTheCartTakesBeyondTheStockIsRefused(): void
    {
        $this->checkStock(true);
        [$customer, $cart, $cartItem] = $this->cartLine(stock: 10);
        $cartItem->setQuantity(8)->save($this->getPropelConnection());

        $response = $this->jsonRequest('POST', '/api/front/cart_items', $this->addition($cart, $cartItem->getProduct(), 3), $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(8.0, $this->reloadQuantity($cartItem->getId()));
    }

    public function testAnAdditionWithinTheStockLeftJoinsTheLineInTheCart(): void
    {
        $this->checkStock(true);
        [$customer, $cart, $cartItem] = $this->cartLine(stock: 10);
        $cartItem->setQuantity(8)->save($this->getPropelConnection());
        $lineId = (int) $cartItem->getId();

        $response = $this->jsonRequest('POST', '/api/front/cart_items', $this->addition($cart, $cartItem->getProduct(), 2), $this->authenticateAsCustomer($customer));

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        // The answer is the line the addition joined, not a new one.
        self::assertSame($lineId, json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['id'] ?? null);
        self::assertSame(10.0, $this->reloadQuantity($lineId));
    }

    /**
     * The line an addition joins is the cart's to pick (CART_FINDITEM), and a module
     * may pick another one than the first the table holds: the stock is checked on
     * the line the cart picked.
     */
    public function testTheStockIsCheckedOnTheLineTheCartJoins(): void
    {
        $this->checkStock(true);
        [$customer, $cart, $first] = $this->cartLine(stock: 10);
        $first->setQuantity(8)->save($this->getPropelConnection());
        $joined = $this->createFixtureFactory()->cartItem($cart, $first->getProduct(), null, ['quantity' => 1]);
        $this->joinThroughAModule($joined);

        $response = $this->jsonRequest('POST', '/api/front/cart_items', $this->addition($cart, $first->getProduct(), 1), $this->authenticateAsCustomer($customer));

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(2.0, $this->reloadQuantity($joined->getId()));
        self::assertSame(8.0, $this->reloadQuantity($first->getId()));
    }

    public function testAnAdditionTheLineTheCartJoinsCannotTakeIsRefused(): void
    {
        $this->checkStock(true);
        [$customer, $cart, $first] = $this->cartLine(stock: 10);
        $joined = $this->createFixtureFactory()->cartItem($cart, $first->getProduct(), null, ['quantity' => 8]);
        $this->joinThroughAModule($joined);

        $response = $this->jsonRequest('POST', '/api/front/cart_items', $this->addition($cart, $first->getProduct(), 3), $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(8.0, $this->reloadQuantity($joined->getId()));
        self::assertSame(1.0, $this->reloadQuantity($first->getId()));
    }

    public function testTheStockDoesNotHoldBackAVirtualProduct(): void
    {
        $this->checkStock(true);
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $product = $this->product($factory, stock: 0);
        $product->setVirtual(1)->save($this->getPropelConnection());

        $response = $this->jsonRequest('POST', '/api/front/cart_items', $this->addition($cart, $product, 5), $this->authenticateAsCustomer($customer));

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testTheStockDoesNotHoldBackAShopThatDoesNotCheckIt(): void
    {
        $this->checkStock(false);
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $product = $this->product($factory, stock: 0);

        $response = $this->jsonRequest('POST', '/api/front/cart_items', $this->addition($cart, $product, 5), $this->authenticateAsCustomer($customer));

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(5.0, (float) $this->onlyLineOf($cart)->getQuantity());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function quantitiesTheCartDoesNotTake(): iterable
    {
        yield 'no quantity' => [[]];
        yield 'null' => [['quantity' => null]];
        yield 'zero' => [['quantity' => 0]];
        yield 'negative' => [['quantity' => -1]];
        yield 'fraction' => [['quantity' => 1.5]];
        yield 'beyond an integer' => [['quantity' => 1.0E+20]];
        yield 'beyond the ceiling' => [['quantity' => 1_000_000]];
    }

    /**
     * @param array<string, mixed> $quantity
     */
    #[DataProvider('quantitiesTheCartDoesNotTake')]
    public function testAnAdditionOfAQuantityTheCartDoesNotTakeAddsNothing(array $quantity): void
    {
        $this->checkStock(false);
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $product = $this->product($factory, stock: 100);
        $addition = array_diff_key($this->addition($cart, $product, 1), ['quantity' => true]) + $quantity;

        $response = $this->jsonRequest('POST', '/api/front/cart_items', $addition, $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
    }

    /**
     * @param array<string, mixed> $quantity
     */
    #[DataProvider('quantitiesTheCartDoesNotTake')]
    public function testAChangeToAQuantityTheCartDoesNotTakeLeavesTheLineAsItWas(array $quantity): void
    {
        $this->checkStock(false);
        [$customer, , $cartItem] = $this->cartLine(stock: 100);

        // Written as an object, so that no quantity still sends a body: the item
        // provider reads the body of every PUT before the processor does.
        $response = $this->rawJsonRequest('PUT', '/api/front/cart_items/'.$cartItem->getId(), json_encode((object) $quantity, \JSON_THROW_ON_ERROR), $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(1.0, $this->reloadQuantity($cartItem->getId()));
    }

    /**
     * A JSON number beyond what a float holds decodes to INF, which no rounding check
     * turns away and which comes out of an integer cast as zero.
     */
    public function testAnInfiniteQuantityIsRefused(): void
    {
        $this->checkStock(false);
        [$customer, $cart, $cartItem] = $this->cartLine(stock: 100);
        $token = $this->authenticateAsCustomer($customer);
        $saleElements = '/api/front/product_sale_elements/'.$cartItem->getProductSaleElementsId();

        $addition = $this->rawJsonRequest('POST', '/api/front/cart_items', '{"cart":"/api/front/carts/'.$cart->getId().'","productSaleElements":"'.$saleElements.'","quantity":1e400}', $token);
        $change = $this->rawJsonRequest('PUT', '/api/front/cart_items/'.$cartItem->getId(), '{"quantity":1e400}', $token);

        self::assertSame(422, $addition->getStatusCode(), (string) $addition->getContent());
        self::assertSame(422, $change->getStatusCode(), (string) $change->getContent());
        self::assertSame(1.0, $this->reloadQuantity($cartItem->getId()));
    }

    /**
     * The ceiling holds the line, not the request: two additions under it do not
     * take the line over it.
     */
    public function testAnAdditionThatTakesTheLineOverTheCeilingIsRefused(): void
    {
        $this->checkStock(false);
        [$customer, $cart, $cartItem] = $this->cartLine(stock: 100);
        $cartItem->setQuantity(600000)->save($this->getPropelConnection());
        $token = $this->authenticateAsCustomer($customer);

        $over = $this->jsonRequest('POST', '/api/front/cart_items', $this->addition($cart, $cartItem->getProduct(), 400000), $token);

        self::assertSame(422, $over->getStatusCode(), (string) $over->getContent());
        self::assertSame(600000.0, $this->reloadQuantity($cartItem->getId()));

        $upTo = $this->jsonRequest('POST', '/api/front/cart_items', $this->addition($cart, $cartItem->getProduct(), 399999), $token);

        self::assertSame(201, $upTo->getStatusCode(), (string) $upTo->getContent());
        self::assertSame(999999.0, $this->reloadQuantity($cartItem->getId()));
    }

    /**
     * Resolving the IRI turns a cart that does not exist away before the cart is
     * asked anything.
     */
    public function testAnAdditionToACartThatDoesNotExistIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $product = $this->product($factory, stock: 100);
        $saleElementsId = (int) $product->getProductSaleElementss()->getFirst()->getId();

        $response = $this->jsonRequest('POST', '/api/front/cart_items', [
            'cart' => '/api/front/carts/999999999',
            'productSaleElements' => '/api/front/product_sale_elements/'.$saleElementsId,
            'quantity' => 1,
        ], $this->authenticateAsCustomer($customer));

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, CartItemQuery::create()->filterByProductSaleElementsId($saleElementsId)->count($this->getPropelConnection()));
    }

    public function testAnAdditionThatNamesNoCartIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $product = $this->product($factory, stock: 100);
        $addition = array_diff_key($this->addition($cart, $product, 1), ['cart' => true]);

        $response = $this->jsonRequest('POST', '/api/front/cart_items', $addition, $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
    }

    public function testAnAdditionThatNamesNoSaleElementIsRefused(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $product = $this->product($factory, stock: 100);
        $addition = array_diff_key($this->addition($cart, $product, 1), ['productSaleElements' => true]);

        $response = $this->jsonRequest('POST', '/api/front/cart_items', $addition, $this->authenticateAsCustomer($customer));

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
    }

    public function testRemovingALineGoesThroughTheCart(): void
    {
        [$customer, , $cartItem] = $this->cartLine(stock: 100);
        $removed = [];
        $this->listen(TheliaEvents::CART_DELETEITEM, static function (CartEvent $event) use (&$removed): void {
            $removed[] = $event->getCartItemId();
        }, 0);

        $response = $this->jsonRequest('DELETE', '/api/front/cart_items/'.$cartItem->getId(), token: $this->authenticateAsCustomer($customer));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame([$cartItem->getId()], $removed);
        self::assertSame(0, CartItemQuery::create()->filterById($cartItem->getId())->count($this->getPropelConnection()));
    }

    /**
     * @return array{0: Customer, 1: Cart, 2: CartItem}
     */
    private function cartLine(int $stock): array
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $cart = $factory->cart($customer);
        $cartItem = $factory->cartItem($cart, $this->product($factory, $stock));

        return [$customer, $cart, $cartItem];
    }

    private function product(FixtureFactory $factory, int $stock): Product
    {
        return $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['baseQuantity' => $stock]);
    }

    /**
     * @return array<string, mixed>
     */
    private function addition(Cart $cart, Product $product, int $quantity, bool $admin = false): array
    {
        return [
            'cart' => '/api/front/carts/'.$cart->getId(),
            'productSaleElements' => '/api/'.($admin ? 'admin' : 'front').'/product_sale_elements/'.$product->getProductSaleElementss()->getFirst()->getId(),
            'quantity' => $quantity,
        ];
    }

    /**
     * A running operation reserved for one customer, set to hide its products from
     * everybody else (`hide_products`).
     */
    private function privateDropOf(FixtureFactory $factory, Product $product, Customer $invited): Sale
    {
        $sale = $factory->sale([
            'active' => true,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'hideProducts' => true,
        ]);
        $factory->saleProduct($sale, $product);
        $factory->saleCustomer($sale, $invited);
        $factory->saleOffsetCurrency($sale, $factory->currency(), 10.0);

        return $sale;
    }

    private function onlyLineOf(Cart $cart): CartItem
    {
        CartItemTableMap::clearInstancePool();
        $lines = CartItemQuery::create()->filterByCartId($cart->getId())->find($this->getPropelConnection());
        self::assertCount(1, $lines);

        return $lines->getFirst();
    }

    private function reloadQuantity(int $cartItemId): ?float
    {
        CartItemTableMap::clearInstancePool();

        return CartItemQuery::create()->findPk($cartItemId, $this->getPropelConnection())?->getQuantity();
    }

    /**
     * A body jsonRequest() cannot write: json_encode() refuses INF.
     */
    private function rawJsonRequest(string $method, string $uri, string $body, string $token): Response
    {
        $this->client->request($method, $uri, server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], content: $body);

        return $this->client->getResponse();
    }

    private function checkStock(bool $check): void
    {
        $this->stockCheckBefore ??= (string) ConfigQuery::read('check-available-stock', '1');
        ConfigQuery::write('check-available-stock', $check ? '1' : '0');
    }

    /**
     * A module answering CART_FINDITEM ahead of the cart, with a line of its choosing.
     */
    private function joinThroughAModule(CartItem $line): void
    {
        $lineId = (int) $line->getId();
        $this->listen(TheliaEvents::CART_FINDITEM, static function (CartEvent $event) use ($lineId): void {
            $event->setCartItem(CartItemQuery::create()->findPk($lineId));
            $event->stopPropagation();
        });
    }

    private function listen(string $eventName, callable $listener, int $priority = 512): void
    {
        $this->dispatcher()->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }
}
