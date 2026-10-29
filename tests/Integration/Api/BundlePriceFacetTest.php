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
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\PriceFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * The price facet judges a bundle priced by its components on the price its card shows: the default
 * composition of the plan, 504 once 10 % are off, and not the 999 of its own sale element.
 */
final class BundlePriceFacetTest extends IntegrationTestCase
{
    use BuildsBundles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getService(PriceFilter::class)->reset();
        $this->getService(PricingActivityChecker::class)->reset();
    }

    protected function tearDown(): void
    {
        $this->getService(PriceFilter::class)->reset();
        $this->getService(PricingActivityChecker::class)->reset();

        parent::tearDown();
    }

    public function testAComputedBundleIsJudgedOnThePriceOfItsComponents(): void
    {
        $factory = $this->createFixtureFactory();
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($factory, $service);
        $example['bundle']->setTaxRuleId(null)->save();
        $reference = $this->component($factory, '520', 5)->setTaxRuleId(null);
        $reference->save();

        self::assertSame([(int) $example['bundle']->getId()], $this->matching([$example['bundle'], $reference], '500', '510'));
        self::assertSame([(int) $reference->getId()], $this->matching([$example['bundle'], $reference], '515', '525'));
    }

    public function testAFixedBundleIsJudgedOnThePriceOfItsOwnSaleElement(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->getService(BundleCompositionService::class), BundlePriceMode::Fixed);
        $example['bundle']->setTaxRuleId(null)->save();

        self::assertSame([(int) $example['bundle']->getId()], $this->matching([$example['bundle']], '990', '1000'));
        self::assertSame([], $this->matching([$example['bundle']], '500', '510'));
    }

    /**
     * @param list<Product> $products
     *
     * @return list<int>
     */
    private function matching(array $products, string $min, string $max): array
    {
        $query = ProductQuery::create()->filterById(array_map(static fn (Product $product): int => (int) $product->getId(), $products), Criteria::IN);
        $filtered = $this->getService(FilterService::class)->filterWithTFilter(
            tfilters: ['price' => ['price' => ['min' => $min, 'max' => $max]]],
            resource: 'products',
            query: $query,
        );

        return array_map(static fn (Product $product): int => (int) $product->getId(), iterator_to_array($filtered->find($this->getPropelConnection())));
    }
}
