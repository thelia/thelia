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

use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * `publicUrl` belongs to the read groups of a product, so the serializer calls
 * the getter once per item of a page and each call resolved a rewritten url of
 * its own. The lookup is memoized per view, locale and id, and the theme's own
 * data path already fills that memo for a whole page: the serializer path did
 * not, and paid one rewriting_url read per product listed.
 */
final class ProductCollectionPublicUrlBatchTest extends ApiTestCase
{
    use RecordsSqlQueries;

    private const PRODUCT_COUNT = 5;

    public function testAPageResolvesItsRewrittenUrlsInOneRead(): void
    {
        $products = $this->catalogue();

        $payload = [];
        $statements = $this->recordSqlQueries(function () use (&$payload): void {
            $payload = $this->readJson('/api/front/products?itemsPerPage='.self::PRODUCT_COUNT);
        });

        foreach ($products as $product) {
            self::assertSame(
                $product->getUrl('en_US'),
                $this->member($payload, $product->getId())['publicUrl'] ?? null,
                'Every product of the page must keep the url it had.',
            );
        }

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'rewriting_url'),
            'A page resolves its rewritten urls in one read per view.',
        );
    }

    /**
     * @return list<Product>
     */
    private function catalogue(): array
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $products = [];

        for ($index = 0; $index < self::PRODUCT_COUNT; ++$index) {
            $products[] = $factory->product(
                $category,
                $taxRule,
                $currency,
                ['title' => 'Listed product '.$index, 'locale' => 'en_US'],
            );
        }

        return $products;
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
