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

use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Resource\Filter;
use Thelia\Api\Resource\FilterValue;
use Thelia\Test\IntegrationTestCase;

/**
 * A shopper who checks a second category widens the listing; the facets that accompany it are
 * read on both. No single category rules them then, so every filter is offered, as outside any
 * category, instead of the request failing on a list of categories where one was expected. A
 * single category keeps obeying its own rules.
 *
 * Catalogue: AISLE-A holds a product of brand Local, AISLE-B one of brand Remote.
 */
final class FacetSeveralCategoriesTest extends IntegrationTestCase
{
    private FilterService $filterService;

    private int $aisleA = 0;

    private int $aisleB = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filterService = static::getContainer()->get(FilterService::class);
        $this->createCatalogue();
    }

    public function testTwoCheckedCategoriesOfferTheFacetsOfBoth(): void
    {
        $facets = $this->facets([[$this->aisleA, $this->aisleB]]);

        self::assertSame(['Local' => 1, 'Remote' => 1], $this->counts($facets, 'brand'));
    }

    public function testOneCheckedCategoryStillObeysItsOwnRules(): void
    {
        // Unchanged: a single category nobody gave filter rules to describes no facet.
        $facets = $this->facets([[$this->aisleA]]);

        self::assertSame([], $this->counts($facets, 'brand'));
    }

    /**
     * @param array<mixed> $categorySelection the value of `tfilters[category]`, as the query string carries it
     *
     * @return array<Filter>
     */
    private function facets(array $categorySelection): array
    {
        return $this->filterService->getFilters(
            [
                'path_info' => '/api/front/products',
                'filters' => [
                    'tfilters' => ['category' => $categorySelection],
                    'locale' => 'en_US',
                    'visible' => 'true',
                ],
            ],
            'products',
        );
    }

    /**
     * @param array<Filter> $facets
     *
     * @return array<string, int>
     */
    private function counts(array $facets, string $type): array
    {
        foreach ($facets as $facet) {
            if ($facet->getType() !== $type) {
                continue;
            }

            $counts = [];

            /** @var FilterValue $value */
            foreach ($facet->getValues() as $value) {
                $counts[(string) $value->getTitle()] = (int) $value->getCount();
            }

            ksort($counts);

            return $counts;
        }

        return [];
    }

    private function createCatalogue(): void
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        foreach (['AISLE-A' => 'Local', 'AISLE-B' => 'Remote'] as $reference => $brandTitle) {
            $category = $factory->category();
            $brand = $factory->brand(['title' => $brandTitle]);
            $product = $factory->product($category, $taxRule, $currency, ['ref' => $reference, 'visible' => 1]);
            $product->setBrandId($brand->getId())->save($connection);

            if ('AISLE-A' === $reference) {
                $this->aisleA = (int) $category->getId();
            } else {
                $this->aisleB = (int) $category->getId();
            }
        }
    }
}
