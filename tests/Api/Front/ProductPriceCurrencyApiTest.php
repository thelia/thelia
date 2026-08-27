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

use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductPrice;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A sale element holds one price row per currency. A front read must answer
 * the one the shop charges in the currency being browsed, converted from the
 * default currency when it has no price of its own.
 */
final class ProductPriceCurrencyApiTest extends ApiTestCase
{
    use RecordsSqlQueries;

    private const CURRENCY_CODE = 'TCU';

    public function testTheFrontReadConvertsThePriceOfTheDefaultCurrency(): void
    {
        $product = $this->product(basePrice: 10.0);
        $this->currency();

        $prices = $this->pricesOfTheFrontRead($product);

        self::assertCount(1, $prices, 'A front read answers the price of one currency, not every row.');
        self::assertSame(20.0, (float) $prices[0]['price'], 'A currency rated 2 doubles the price of the default currency.');
        self::assertSame(self::CURRENCY_CODE, $prices[0]['currency']['code'] ?? null);
    }

    public function testThePriceSetForTheCurrencyIsAnsweredAsIs(): void
    {
        $product = $this->product(basePrice: 10.0);
        $currency = $this->currency();
        $this->priceRow($product, $currency, price: 9.99, promoPrice: 7.99, fromDefaultCurrency: false);

        $prices = $this->pricesOfTheFrontRead($product);

        self::assertCount(1, $prices);
        self::assertSame(9.99, (float) $prices[0]['price'], 'A price of its own is what the shop charges.');
        self::assertSame(7.99, (float) $prices[0]['promoPrice']);
    }

    public function testAPriceFlaggedAsDerivedIsConverted(): void
    {
        $product = $this->product(basePrice: 10.0);
        $currency = $this->currency();
        // What the back office stores for "use the default currency price".
        $this->priceRow($product, $currency, price: 0.0, promoPrice: 0.0, fromDefaultCurrency: true);

        $prices = $this->pricesOfTheFrontRead($product);

        self::assertCount(1, $prices);
        self::assertSame(20.0, (float) $prices[0]['price'], 'A derived row is converted, not answered as the zero it stores.');
        self::assertSame(self::CURRENCY_CODE, $prices[0]['currency']['code'] ?? null);
    }

    public function testTheAdminReadKeepsEveryCurrency(): void
    {
        $product = $this->product(basePrice: 10.0);
        $currency = $this->currency();
        $this->priceRow($product, $currency, price: 9.99, promoPrice: 7.99, fromDefaultCurrency: false);

        $response = $this->jsonRequest(
            'GET',
            '/api/admin/product_sale_elements/'.$product->getDefaultSaleElements()->getId().'?currency='.self::CURRENCY_CODE,
            token: $this->authenticateAsAdmin(),
        );

        self::assertJsonResponseSuccessful($response);
        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertCount(
            2,
            $payload['productPrices'] ?? [],
            'The back office edits one price per currency: it must be answered all of them.',
        );
    }

    public function testTheCollectionResolvesThePricesWithoutAQueryPerSaleElement(): void
    {
        $this->currency();
        $products = [];

        for ($index = 0; $index < 3; ++$index) {
            $products[] = $this->product(basePrice: 10.0);
        }

        $statements = $this->recordSqlQueries(function (): void {
            $response = $this->jsonRequest('GET', '/api/front/products?currency='.self::CURRENCY_CODE);
            self::assertJsonResponseSuccessful($response);
        });

        self::assertLessThanOrEqual(
            \count($products),
            self::countSqlQueriesSelectingFrom($statements, 'product_price'),
            'Resolving the currency of a price must not read the rows a second time.',
        );
        self::assertLessThanOrEqual(
            2,
            self::countSqlQueriesSelectingFrom($statements, 'currency'),
            'The currency being browsed is read once for the whole request, not once per sale element.',
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pricesOfTheFrontRead(Product $product): array
    {
        $response = $this->jsonRequest(
            'GET',
            '/api/front/products/'.$product->getId().'?currency='.self::CURRENCY_CODE,
        );

        self::assertJsonResponseSuccessful($response);
        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $payload['productSaleElements'][0]['productPrices'] ?? [];
    }

    private function product(float $basePrice): Product
    {
        $factory = $this->createFixtureFactory();

        return $factory->product(
            $factory->category(),
            $factory->taxRule(),
            Currency::getDefaultCurrency(),
            ['basePrice' => $basePrice],
        );
    }

    /**
     * Rated twice the default currency, whatever rate that one is given: the
     * suite shares the default currency and does not always leave it at 1.
     */
    private function currency(): Currency
    {
        return $this->createFixtureFactory()->currency([
            'code' => self::CURRENCY_CODE,
            'symbol' => 'T',
            'rate' => 2.0 * Currency::getDefaultCurrency()->getRate(),
            'visible' => 1,
        ]);
    }

    private function priceRow(
        Product $product,
        Currency $currency,
        float $price,
        float $promoPrice,
        bool $fromDefaultCurrency,
    ): ProductPrice {
        $productPrice = new ProductPrice();
        $productPrice
            ->setProductSaleElementsId($product->getDefaultSaleElements()->getId())
            ->setCurrencyId($currency->getId())
            ->setPrice((string) $price)
            ->setPromoPrice((string) $promoPrice)
            ->setFromDefaultCurrency($fromDefaultCurrency ? 1 : 0)
            ->save($this->getPropelConnection());

        return $productPrice;
    }
}
