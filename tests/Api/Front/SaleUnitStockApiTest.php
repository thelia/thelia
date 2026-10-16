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
use Thelia\Domain\SaleUnit\Resolver\ApiHeaderSaleUnitResolver;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\SaleUnit;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The front API offers the stock of the sale unit the caller buys from: the
 * quantity of a sale element is the unit's own when it keeps one, and a cart line
 * is judged against it. A caller that names no unit reads the national counter.
 */
final class SaleUnitStockApiTest extends ApiTestCase
{
    private const NATIONAL_STOCK = 100;
    private const UNIT_STOCK = 4;

    private FixtureFactory $factory;

    private Product $product;

    private ProductSaleElements $productSaleElements;

    private SaleUnit $saleUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => self::NATIONAL_STOCK],
        );
        $this->productSaleElements = $this->product->getProductSaleElementss()->getFirst();
        $this->saleUnit = $this->factory->saleUnit(['code' => 'stock-unit', 'url' => null]);
        $this->factory->saleUnitProductSaleElements($this->saleUnit, $this->productSaleElements, self::UNIT_STOCK);
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheSaleElementShowsTheStockOfTheUnitTheCallerBuysFrom(): void
    {
        $response = $this->frontRequest('GET', '/api/front/product_sale_elements/'.$this->productSaleElements->getId(), saleUnitCode: 'stock-unit');

        self::assertJsonResponseSuccessful($response);
        self::assertSame(self::UNIT_STOCK, $this->decode($response)['quantity']);
    }

    public function testACallerNamingNoUnitReadsTheNationalStock(): void
    {
        $response = $this->frontRequest('GET', '/api/front/product_sale_elements/'.$this->productSaleElements->getId());

        self::assertJsonResponseSuccessful($response);
        self::assertSame(self::NATIONAL_STOCK, $this->decode($response)['quantity']);
    }

    public function testAnInactiveUnitNamedByTheHeaderIsNotServed(): void
    {
        $this->saleUnit->setActive(false)->save($this->getPropelConnection());
        $this->factory->saleUnit();

        $response = $this->frontRequest('GET', '/api/front/product_sale_elements/'.$this->productSaleElements->getId(), saleUnitCode: 'stock-unit');

        self::assertJsonResponseSuccessful($response);
        self::assertSame(self::NATIONAL_STOCK, $this->decode($response)['quantity'], 'A turned-off unit is never resolved from the header.');
    }

    public function testACartLineBeyondTheUnitStockIsRefused(): void
    {
        ConfigQuery::write('check-available-stock', '1');
        $customer = $this->factory->customer($this->factory->customerTitle(), ['password' => 'password']);
        $cart = $this->factory->cart($customer);

        $response = $this->frontRequest('POST', '/api/front/cart_items', [
            'cart' => '/api/front/carts/'.$cart->getId(),
            'productSaleElements' => '/api/front/product_sale_elements/'.$this->productSaleElements->getId(),
            'quantity' => self::UNIT_STOCK + 1,
        ], $this->authenticateAsCustomer($customer), 'stock-unit');

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, CartItemQuery::create()->filterByCartId($cart->getId())->count($this->getPropelConnection()));
    }

    public function testACartLineTheUnitStockCoversIsAcceptedThoughTheNationalCounterIsShort(): void
    {
        ConfigQuery::write('check-available-stock', '1');
        $this->productSaleElements->setQuantity(0)->save($this->getPropelConnection());
        $customer = $this->factory->customer($this->factory->customerTitle(), ['password' => 'password']);
        $cart = $this->factory->cart($customer);

        $response = $this->frontRequest('POST', '/api/front/cart_items', [
            'cart' => '/api/front/carts/'.$cart->getId(),
            'productSaleElements' => '/api/front/product_sale_elements/'.$this->productSaleElements->getId(),
            'quantity' => self::UNIT_STOCK,
        ], $this->authenticateAsCustomer($customer), 'stock-unit');

        self::assertJsonResponseSuccessful($response);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function frontRequest(string $method, string $uri, array $payload = [], ?string $token = null, ?string $saleUnitCode = null): Response
    {
        $server = [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ];

        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        if (null !== $saleUnitCode) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', ApiHeaderSaleUnitResolver::HEADER))] = $saleUnitCode;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: [] === $payload ? null : json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        return $this->client->getResponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
