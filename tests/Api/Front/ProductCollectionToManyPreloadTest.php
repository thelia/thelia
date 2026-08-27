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

use Propel\Runtime\Propel;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductPrice;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A collection cannot join its to-many relations without duplicating the rows
 * it paginates, so the query reads the parents alone and each parent was then
 * asked for its children on its own: one read of product_sale_elements per
 * product, one read of product_price per sale element. A page therefore cost
 * more as the catalogue grew, for rows a single statement per relation
 * returns.
 */
final class ProductCollectionToManyPreloadTest extends ApiTestCase
{
    use RecordsSqlQueries;

    private const PRODUCT_COUNT = 4;

    public function testEachToManyRelationIsReadOnceForTheWholePage(): void
    {
        $products = $this->catalogue();

        $payload = [];
        $statements = $this->withInstancePooling(function () use (&$payload): void {
            $payload = $this->readJson('/api/front/products');
        });

        $reads = [
            'product_sale_elements' => self::countSqlQueriesSelectingFrom($statements, 'product_sale_elements'),
            'product_price' => self::countSqlQueriesSelectingFrom($statements, 'product_price'),
        ];

        self::assertSame(
            ['product_sale_elements' => 1, 'product_price' => 1],
            $reads,
            'A to-many relation of a collection is one read for the page, not one read per row.',
        );

        // The rows must be the same ones, only fetched together.
        foreach ($products as $product) {
            $member = $this->member($payload, $product->getId());

            self::assertCount(2, $member['productSaleElements'] ?? []);

            foreach ($member['productSaleElements'] as $saleElement) {
                self::assertNotEmpty($saleElement['productPrices'] ?? []);
            }
        }
    }

    /**
     * Instance pooling is what hands a preloaded row back to its parent, and
     * the test suite turns it off to keep rolled back rows out of the next
     * test. Turn it back on around the measured request only, then empty the
     * pools it filled.
     *
     * @return list<string>
     */
    private function withInstancePooling(callable $work): array
    {
        $wasEnabled = Propel::isInstancePoolingEnabled();
        Propel::enableInstancePooling();

        try {
            return $this->recordSqlQueries($work);
        } finally {
            foreach (Propel::getServiceContainer()->getDatabaseMap('thelia')->getTables() as $tableMap) {
                $tableMap->clearInstancePool();
            }

            if (!$wasEnabled) {
                Propel::disableInstancePooling();
            }
        }
    }

    /**
     * @return list<Product>
     */
    private function catalogue(): array
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();

        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $products = [];

        for ($i = 0; $i < self::PRODUCT_COUNT; ++$i) {
            $product = $factory->product($category, $taxRule, $currency);

            // Product::create() already made one sale element with its price;
            // a second one makes the per-row reads impossible to confuse with
            // a per-product one.
            $saleElement = $factory->productSaleElement($product);
            $this->priceFor($saleElement->getId(), $currency, $connection);

            $products[] = $product;
        }

        return $products;
    }

    private function priceFor(int $saleElementId, Currency $currency, $connection): void
    {
        $price = new ProductPrice();
        $price->setProductSaleElementsId($saleElementId);
        $price->setCurrencyId($currency->getId());
        $price->setPrice('12.000000');
        $price->setPromoPrice('0.000000');
        $price->save($connection);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $uri): array
    {
        $response = $this->jsonRequest('GET', $uri);
        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function member(array $payload, ?int $productId): array
    {
        foreach ($payload['hydra:member'] ?? [] as $member) {
            if (($member['id'] ?? null) === $productId) {
                return $member;
            }
        }

        self::fail('The collection must return the products under test, otherwise nothing is measured.');
    }
}
