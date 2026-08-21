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

namespace Thelia\Tests\Integration\Api;

use Thelia\Api\Service\DataAccess\DataAccessService;
use Thelia\Model\Product;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The Flexy theme fetches its data through the `resources()` Twig function,
 * which calls {@see DataAccessService::resources()} in-process (it "can't use
 * [the] Serializer in this use case", per ResourceService::doResources()) and
 * adds `publicUrl` itself, one item at a time. None of it was memoized within
 * the request and none of it was batched across a collection: 65 rewriting_url
 * reads for 27 distinct keys on the demo home page.
 */
final class ProductCollectionRewritingUrlQueryCountTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private DataAccessService $dataAccess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataAccess = static::getContainer()->get(DataAccessService::class);
    }

    public function testACollectionBatchesItsRewrittenUrlLookupsInsteadOfOnePerItem(): void
    {
        $products = $this->createProductsWithTitles([
            'Wooden chair',
            'Steel table',
            'Glass vase',
            'Ceramic mug',
            'Cotton towel',
        ]);

        $payload = [];
        $statements = $this->recordSqlQueries(function () use (&$payload): void {
            $payload = $this->dataAccess->resources('/api/front/products');
        });

        self::assertIsArray($payload);

        foreach ($products as $product) {
            self::assertNotNull(
                $this->member($payload, $product->getId())['publicUrl'] ?? null,
                'Every product of the collection must keep getting a public url.',
            );
        }

        $rewritingUrlQueries = self::countSqlQueriesSelectingFrom($statements, 'rewriting_url');

        self::assertGreaterThan(
            0,
            $rewritingUrlQueries,
            'The collection does resolve rewritten urls, so it must read the table at least once.',
        );
        self::assertLessThan(
            \count($products),
            $rewritingUrlQueries,
            'One query per distinct view batches every item of the collection: it must cost strictly less than one query per product.',
        );
    }

    /**
     * @param list<string> $titles
     *
     * @return list<Product>
     */
    private function createProductsWithTitles(array $titles): array
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $products = [];

        foreach ($titles as $title) {
            $product = $factory->product($category, $taxRule, $currency);
            $product->setLocale('en_US')->setTitle($title)->save();
            $products[] = $product;
        }

        return $products;
    }

    /**
     * @param array<int, array<string, mixed>> $payload
     *
     * @return array<string, mixed>
     */
    private function member(array $payload, int $productId): array
    {
        foreach ($payload as $entry) {
            if (($entry['id'] ?? null) === $productId) {
                return $entry;
            }
        }

        self::fail('The collection must return the product under test, otherwise nothing is measured.');
    }
}
