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

use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A paginated collection needs its total once, to say how many items there
 * are. The pagination extension took it before reading the page and threw the
 * answer away: the offset it fed is never used, and the pager counts the rows
 * again a moment later. The page therefore ran the heaviest statement it has
 * twice, a COUNT over the whole filtered catalogue.
 */
final class ProductCollectionPaginationCountTest extends ApiTestCase
{
    use RecordsSqlQueries;

    private const PRODUCT_COUNT = 3;

    public function testAPaginatedCollectionCountsItsTotalOnce(): void
    {
        $this->catalogue();

        $payload = [];
        $statements = $this->recordSqlQueries(function () use (&$payload): void {
            $payload = $this->readJson('/api/front/products?itemsPerPage=2');
        });

        self::assertCount(2, $payload['hydra:member'] ?? [], 'The page must be the one that was asked for.');
        self::assertGreaterThanOrEqual(
            self::PRODUCT_COUNT,
            $payload['hydra:totalItems'] ?? 0,
            'The total must still be answered.',
        );

        self::assertSame(
            1,
            self::countCounts($statements),
            'The total of a page is counted once: '.implode("\n", $statements),
        );
    }

    /**
     * @param list<string> $statements
     */
    private static function countCounts(array $statements): int
    {
        return \count(array_filter(
            $statements,
            static fn (string $statement): bool => str_contains($statement, 'COUNT('),
        ));
    }

    private function catalogue(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        for ($index = 0; $index < self::PRODUCT_COUNT; ++$index) {
            $factory->product($category, $taxRule, $currency);
        }
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
}
