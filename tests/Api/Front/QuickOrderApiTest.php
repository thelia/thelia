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
use Thelia\Model\CartItemQuery;
use Thelia\Model\Customer;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * Ordering by reference through the API: an account resolves lines without the
 * cart changing, then adds them to one of its own carts. Everything else is
 * refused before the catalog is read.
 */
final class QuickOrderApiTest extends ApiTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    public function testResolvingAnswersWithTheControlTableAndLeavesTheCartAlone(): void
    {
        $customer = $this->customer();
        $cart = $this->factory->cart($customer);
        $saleElements = $this->saleElements();

        $response = $this->quickOrder('/api/front/account/quick-order/resolve', $customer, [
            ['reference' => (string) $saleElements->getRef(), 'quantity' => 2],
            ['reference' => 'NOT-IN-THE-CATALOG', 'quantity' => 1],
        ]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $table = self::decode($response);
        self::assertSame(['resolved', 'unknown'], array_column($table['lines'], 'status'));
        self::assertSame((int) $saleElements->getId(), $table['lines'][0]['productSaleElementsId']);
        self::assertSame(1, $table['summary']['resolved']);
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testAddingPutsTheResolvedLinesInTheAccountCart(): void
    {
        $customer = $this->customer();
        $cart = $this->factory->cart($customer);
        $saleElements = $this->saleElements();

        $response = $this->quickOrder('/api/front/account/quick-order/'.$cart->getId().'/add', $customer, [
            ['reference' => (string) $saleElements->getRef(), 'quantity' => 3],
            ['reference' => 'NOT-IN-THE-CATALOG', 'quantity' => 1],
        ]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([true, false], array_column(self::decode($response)['lines'], 'added'));

        $item = CartItemQuery::create()->filterByCartId($cart->getId())->findOne();
        self::assertNotNull($item);
        self::assertSame((int) $saleElements->getId(), (int) $item->getProductSaleElementsId());
        self::assertSame(3.0, (float) $item->getQuantity());
    }

    public function testTheCartOfAnotherAccountAnswersAsIfItDidNotExist(): void
    {
        $cart = $this->factory->cart($this->customer());

        $response = $this->quickOrder('/api/front/account/quick-order/'.$cart->getId().'/add', $this->customer(), [
            ['reference' => (string) $this->saleElements()->getRef(), 'quantity' => 1],
        ]);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testAnAnonymousCallerIsRefused(): void
    {
        $cart = $this->factory->cart($this->customer());
        $lines = ['lines' => [['reference' => 'ANY', 'quantity' => 1]]];

        self::assertSame(401, $this->jsonRequest('POST', '/api/front/account/quick-order/resolve', $lines, format: 'json')->getStatusCode());
        self::assertSame(401, $this->jsonRequest('POST', '/api/front/account/quick-order/'.$cart->getId().'/add', $lines, format: 'json')->getStatusCode());
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>}>
     */
    public static function refusedBodies(): iterable
    {
        yield 'no line' => [[]];
        yield 'no reference' => [[['reference' => '', 'quantity' => 1]]];
        yield 'zero quantity' => [[['reference' => 'ABC', 'quantity' => 0]]];
        yield 'quantity as text' => [[['reference' => 'ABC', 'quantity' => 'two']]];
        yield 'a price chosen by the caller' => [[['reference' => 'ABC', 'quantity' => 1, 'price' => 0.01]]];
        yield 'a quantity above the maximum' => [[['reference' => 'ABC', 'quantity' => 3000000000]]];
        yield 'two lines of one reference above the maximum once added up' => [[['reference' => 'ABC', 'quantity' => 600000], ['reference' => 'ABC', 'quantity' => 600000]]];
        yield 'more than five hundred lines' => [array_map(static fn (int $n): array => ['reference' => 'REF-'.$n, 'quantity' => 1], range(1, 501))];
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refusedBodies')]
    public function testABodyTheShopCannotTakeIsRefused(array $lines): void
    {
        $response = $this->quickOrder('/api/front/account/quick-order/resolve', $this->customer(), $lines);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testAnAccountIsCappedToThirtyRequestsAMinute(): void
    {
        $customer = $this->customer();
        $token = $this->authenticateAsCustomer($customer);
        $body = ['lines' => [['reference' => 'ANY', 'quantity' => 1]]];

        for ($n = 1; $n <= 30; ++$n) {
            self::assertSame(200, $this->jsonRequest('POST', '/api/front/account/quick-order/resolve', $body, $token, 'json')->getStatusCode(), 'Request '.$n.' was refused.');
        }

        self::assertSame(429, $this->jsonRequest('POST', '/api/front/account/quick-order/resolve', $body, $token, 'json')->getStatusCode());
    }

    public function testTheResourceHasNoReadableItem(): void
    {
        $cart = $this->factory->cart($this->customer());

        self::assertSame(404, $this->jsonRequest('GET', '/api/quick_orders/'.$cart->getId(), format: 'json')->getStatusCode());
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function quickOrder(string $uri, Customer $customer, array $lines): Response
    {
        return $this->jsonRequest('POST', $uri, ['lines' => $lines], $this->authenticateAsCustomer($customer), 'json');
    }

    /**
     * @return array{lines: list<array<string, mixed>>, summary: array<string, int>}
     */
    private static function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    private function saleElements(): ProductSaleElements
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 50]);

        return $product->getProductSaleElementss()->getFirst()
            ?? throw new \LogicException('The product has no sale element.');
    }
}
