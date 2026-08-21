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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Model\Feature;
use Thelia\Model\FeatureProduct;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * Two values checked inside one feature ask for either of them; values checked across two
 * features ask for both. A shopper who checks "Blue" and then "Red" expects more products,
 * never an empty list.
 */
final class FeatureFilterSelectionTest extends IntegrationTestCase
{
    private const BLUE_PRODUCT = 'FEAT-BLUE';
    private const RED_PRODUCT = 'FEAT-RED';
    private const BLUE_FRENCH_PRODUCT = 'FEAT-BLUE-FRENCH';
    private const FRENCH_PRODUCT = 'FEAT-FRENCH';

    private FilterService $filterService;

    /** @var array<string, int> */
    private array $featureAvIds = [];

    /** @var array<string, int> product reference => id, in creation order */
    private array $productIds = [];

    private int $colourId = 0;
    private int $originId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filterService = static::getContainer()->get(FilterService::class);
        $this->createCatalogue();
    }

    public function testOneCheckedValueKeepsTheProductsHoldingIt(): void
    {
        self::assertSame(
            [self::BLUE_PRODUCT, self::BLUE_FRENCH_PRODUCT],
            $this->matchingReferences([$this->colourId => [$this->featureAvIds['blue']]]),
        );
    }

    public function testTwoCheckedValuesOfTheSameFeatureWidenTheSelection(): void
    {
        self::assertSame(
            [self::BLUE_PRODUCT, self::RED_PRODUCT, self::BLUE_FRENCH_PRODUCT],
            $this->matchingReferences([
                $this->colourId => [
                    $this->featureAvIds['blue'],
                    $this->featureAvIds['red'],
                ],
            ]),
        );
    }

    public function testCheckedValuesOfTwoFeaturesNarrowTheSelection(): void
    {
        self::assertSame(
            [self::BLUE_FRENCH_PRODUCT],
            $this->matchingReferences([
                $this->colourId => [$this->featureAvIds['blue']],
                $this->originId => [$this->featureAvIds['french']],
            ]),
        );
    }

    /**
     * @param array<int, list<int>> $selection feature id => checked value ids
     *
     * @return list<string> the references of the matching products, in creation order
     */
    private function matchingReferences(array $selection): array
    {
        // Restricted to the products this test created: the filter is what is under test,
        // not whatever else the database holds.
        $query = ProductQuery::create()->filterById(array_values($this->productIds), Criteria::IN);

        $filtered = $this->filterService->filterWithTFilter(
            tfilters: ['feature' => $selection],
            resource: 'products',
            query: $query,
        );

        $matched = [];

        foreach ($filtered->find($this->getPropelConnection()) as $product) {
            $matched[] = (int) $product->getId();
        }

        return array_keys(array_filter(
            $this->productIds,
            static fn (int $id): bool => \in_array($id, $matched, true),
        ));
    }

    private function createCatalogue(): void
    {
        $factory = $this->createFixtureFactory();

        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $colour = $factory->feature(['title' => 'Colour']);
        $origin = $factory->feature(['title' => 'Origin']);
        $this->colourId = (int) $colour->getId();
        $this->originId = (int) $origin->getId();

        $blue = $factory->featureAv($colour, ['title' => 'Blue']);
        $red = $factory->featureAv($colour, ['title' => 'Red']);
        $french = $factory->featureAv($origin, ['title' => 'France']);

        $this->featureAvIds = [
            'blue' => (int) $blue->getId(),
            'red' => (int) $red->getId(),
            'french' => (int) $french->getId(),
        ];

        $catalogue = [
            self::BLUE_PRODUCT => [[$colour, $blue]],
            self::RED_PRODUCT => [[$colour, $red]],
            self::BLUE_FRENCH_PRODUCT => [[$colour, $blue], [$origin, $french]],
            self::FRENCH_PRODUCT => [[$origin, $french]],
        ];

        foreach ($catalogue as $reference => $heldValues) {
            $product = $factory->product($category, $taxRule, $currency, ['ref' => $reference]);
            $this->productIds[$reference] = (int) $product->getId();

            foreach ($heldValues as [$feature, $featureAv]) {
                $this->holdValue($product, $feature, (int) $featureAv->getId());
            }
        }
    }

    private function holdValue(Product $product, Feature $feature, int $featureAvId): void
    {
        $featureProduct = new FeatureProduct();
        $featureProduct->setProductId($product->getId());
        $featureProduct->setFeatureId($feature->getId());
        $featureProduct->setFeatureAvId($featureAvId);
        $featureProduct->save($this->getPropelConnection());
    }
}
