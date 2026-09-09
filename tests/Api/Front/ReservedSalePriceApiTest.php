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

use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\Sale;
use Thelia\Model\TaxRule;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A reserved operation writes nothing in the catalog, so the price it gives a
 * named customer only exists as the answer to "what does this customer pay".
 *
 * The front read has to give that answer to the customer it belongs to, and the
 * catalog price to everybody else.
 */
final class ReservedSalePriceApiTest extends ApiTestCase
{
    use RecordsSqlQueries;

    private Currency $currency;
    private Category $category;
    private TaxRule $taxRule;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->currency = $factory->currency();
        $this->category = $factory->category();
        $this->taxRule = $factory->taxRule();
    }

    public function testTheNamedCustomerIsChargedTheReservedPrice(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->newCustomer('password');
        $this->reservedSaleOn([$product], $customer, offset: 10.0);

        $payload = $this->readJson(
            '/api/front/products/'.$product->getId(),
            $this->authenticateAsCustomer($customer),
        );

        $saleElement = $payload['productSaleElements'][0];

        self::assertTrue($saleElement['promo'], 'The reserved price is a promo for the customer it is resolved for.');
        self::assertSame(90.0, (float) $saleElement['productPrices'][0]['promoPrice']);
        self::assertSame(100.0, (float) $saleElement['productPrices'][0]['price'], 'The catalog price is untouched.');
    }

    public function testACustomerTheOperationDoesNotNameIsChargedTheCatalogPrice(): void
    {
        $product = $this->catalogProduct();
        $this->reservedSaleOn([$product], $this->newCustomer(), offset: 10.0);

        $payload = $this->readJson(
            '/api/front/products/'.$product->getId(),
            $this->authenticateAsCustomer($this->newCustomer('password')),
        );

        $saleElement = $payload['productSaleElements'][0];

        self::assertFalse($saleElement['promo']);
        self::assertSame(100.0, (float) $saleElement['productPrices'][0]['promoPrice']);
    }

    public function testAVisitorIsChargedTheCatalogPrice(): void
    {
        $product = $this->catalogProduct();
        $this->reservedSaleOn([$product], $this->newCustomer(), offset: 10.0);

        $payload = $this->readJson('/api/front/products/'.$product->getId());

        self::assertFalse($payload['productSaleElements'][0]['promo']);
        self::assertSame(100.0, (float) $payload['productSaleElements'][0]['productPrices'][0]['promoPrice']);
    }

    public function testTheSaleElementReadCarriesTheReservedPriceToo(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->newCustomer('password');
        $this->reservedSaleOn([$product], $customer, offset: 10.0);
        $token = $this->authenticateAsCustomer($customer);
        $saleElementId = $product->getDefaultSaleElements()->getId();

        $collection = $this->readJson(
            '/api/front/product_sale_elements?product.id='.$product->getId(),
            $token,
        );

        self::assertTrue(
            $collection['hydra:member'][0]['promo'],
            'The collection flags the sale element as discounted for the customer it is reserved for.',
        );

        // The price rows themselves only travel with the single read: the collection
        // serializes them as IRIs.
        $item = $this->readJson('/api/front/product_sale_elements/'.$saleElementId, $token);

        self::assertTrue($item['promo']);
        self::assertSame(90.0, (float) $item['productPrices'][0]['promoPrice']);
    }

    /**
     * The reserved price only ever applies when it beats the price the sale element
     * is on sale for already: the shop never charges a named customer more than a
     * passing visitor.
     */
    public function testAReservedPriceDearerThanThePublicOfferIsNotApplied(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->newCustomer('password');
        $this->reservedSaleOn([$product], $customer, offset: 5.0);
        $this->publicOfferOn($product, promoPrice: 50.0);

        $payload = $this->readJson(
            '/api/front/products/'.$product->getId(),
            $this->authenticateAsCustomer($customer),
        );

        self::assertSame(50.0, (float) $payload['productSaleElements'][0]['productPrices'][0]['promoPrice']);
    }

    /**
     * The whole page has to be priced in one go. A resolution hanging off each sale
     * element costs one statement per row, and the bill grows with the catalog.
     */
    public function testAPageOfProductsIsPricedWithoutOneStatementPerSaleElement(): void
    {
        $customer = $this->newCustomer('password');
        $products = [
            $this->catalogProduct(),
            $this->catalogProduct(),
            $this->catalogProduct(),
            $this->catalogProduct(),
            $this->catalogProduct(),
        ];
        $this->reservedSaleOn($products, $customer, offset: 10.0);
        $token = $this->authenticateAsCustomer($customer);

        $payload = [];
        $statements = $this->recordSqlQueries(function () use (&$payload, $token): void {
            $payload = $this->readJson('/api/front/products', $token);
        });

        $priced = 0;

        foreach ($payload['hydra:member'] as $member) {
            foreach ($member['productSaleElements'] ?? [] as $saleElement) {
                if (true === ($saleElement['promo'] ?? null)) {
                    ++$priced;
                }
            }
        }

        self::assertSame(5, $priced, 'Every sale element of the operation has to come out priced.');

        $resolutions = \count(array_filter(
            $statements,
            static fn (string $statement): bool => str_contains($statement, 'sale_offset_currency'),
        ));

        self::assertSame(
            1,
            $resolutions,
            'The reserved prices of a whole page are resolved by a single statement.',
        );
    }

    private function catalogProduct(): Product
    {
        return $this->createFixtureFactory()->product(
            $this->category,
            $this->taxRule,
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => 100.0, 'title' => 'Reserved price product'],
        );
    }

    /**
     * @param list<Product> $products
     */
    private function reservedSaleOn(array $products, Customer $customer, float $offset): Sale
    {
        $factory = $this->createFixtureFactory();
        $sale = $factory->sale([
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'priceOffsetType' => Sale::OFFSET_TYPE_PERCENTAGE,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);

        foreach ($products as $product) {
            $factory->saleProduct($sale, $product);
        }

        $factory->saleCustomer($sale, $customer);
        $factory->saleOffsetCurrency($sale, $this->currency, $offset);

        return $sale;
    }

    /**
     * A public special offer, written in the catalog the way Action\Sale writes one.
     */
    private function publicOfferOn(Product $product, float $promoPrice): void
    {
        foreach ($product->getProductSaleElementss() as $saleElement) {
            $saleElement->setPromo(1)->save();

            // product_price.promo_price is a DECIMAL, which Propel types as a string.
            foreach ($saleElement->getProductPrices() as $price) {
                $price->setPromoPrice(number_format($promoPrice, 6, '.', ''))->save();
            }
        }
    }

    private function newCustomer(?string $password = null): Customer
    {
        $factory = $this->createFixtureFactory();

        return $factory->customer(
            $factory->customerTitle(),
            null === $password ? [] : ['password' => $password],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $uri, ?string $token = null): array
    {
        $response = $this->jsonRequest('GET', $uri, token: $token);
        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
