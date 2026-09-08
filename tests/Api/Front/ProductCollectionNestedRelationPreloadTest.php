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
use Thelia\Model\Lang;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A collection reads its to-many relations in one query each, and then each of
 * the rows those queries returned went back to the database on its own: the
 * category behind a product link, and one translation of it per active
 * language. A page of products listed under five categories spent twenty-five
 * queries reaching them.
 */
final class ProductCollectionNestedRelationPreloadTest extends ApiTestCase
{
    use ForgetsPooledModels;
    use RecordsSqlQueries;

    private const CATEGORY_COUNT = 5;

    public function testTheCategoryBehindEachProductLinkIsReadOnceForThePage(): void
    {
        $categories = $this->catalogue();

        $payload = [];
        $statements = $this->recordSqlQueriesWithoutPooledModels(function () use (&$payload): void {
            $payload = $this->readJson('/api/front/products?itemsPerPage='.self::CATEGORY_COUNT);
        });

        $reads = [
            'category' => self::countSqlQueriesSelectingFrom($statements, 'category'),
            'category_i18n' => self::countSqlQueriesSelectingFrom($statements, 'category_i18n'),
        ];

        self::assertSame(
            ['category' => 1, 'category_i18n' => 1],
            $reads,
            'The categories of a page and their translations are one read each, not one read per product.',
        );

        // The rows must be the same ones, only fetched together.
        $titles = [];

        foreach ($payload['hydra:member'] as $member) {
            foreach ($member['productCategories'] as $productCategory) {
                $titles[] = $productCategory['category']['i18ns']['en_US']['title'] ?? null;
            }
        }

        sort($titles);

        self::assertSame(
            array_map(
                static fn (Category $category): string => 'Category '.$category->getId().' en_US',
                $categories,
            ),
            $titles,
        );
    }

    /**
     * @return list<Category>
     */
    private function catalogue(): array
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $categories = [];

        for ($index = 0; $index < self::CATEGORY_COUNT; ++$index) {
            $category = $factory->category();

            foreach (Lang::getActiveLangs() as $lang) {
                $category
                    ->setLocale((string) $lang->getLocale())
                    ->setTitle('Category '.$category->getId().' '.$lang->getLocale());
            }

            $category->save($connection);
            $factory->product($category, $taxRule, $currency);

            $categories[] = $category;
        }

        return $categories;
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
