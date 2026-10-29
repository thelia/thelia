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

namespace Thelia\Tests\Integration\Core\Template;

use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Model\Currency;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * The loops show a bundle priced by its components at the price of its default composition:
 * 560 struck, 504 once 10 % are off (bike 1, two helmets, no lock).
 */
final class BundleLoopPriceTest extends IntegrationTestCase
{
    use BuildsBundles;

    private BundleCompositionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(BundleCompositionService::class);
        $this->getService(PricingActivityChecker::class)->reset();
    }

    protected function tearDown(): void
    {
        $this->getService(PricingActivityChecker::class)->reset();

        parent::tearDown();
    }

    public function testTheProductLoopShowsThePriceOfTheComponents(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->service);

        $row = $this->firstRow('product', ['id' => $example['bundle']->getId()]);

        self::assertEqualsWithDelta(560.0, (float) $row['PRICE'], 0.000001);
        self::assertEqualsWithDelta(504.0, (float) $row['PROMO_PRICE'], 0.000001);
        self::assertEquals(1, $row['IS_PROMO']);
        self::assertEqualsWithDelta(504.0, (float) $row['BEST_PRICE'], 0.000001);
    }

    public function testTheComplexProductLoopShowsThePriceOfTheComponents(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->service);

        $row = $this->firstRow('product', ['id' => $example['bundle']->getId(), 'complex' => true]);

        self::assertEqualsWithDelta(504.0, (float) $row['BEST_PRICE'], 0.000001);
    }

    public function testTheSaleElementsLoopShowsThePriceOfTheComponents(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->service);

        $row = $this->firstRow('product_sale_elements', ['product' => $example['bundle']->getId()]);

        self::assertEqualsWithDelta(560.0, (float) $row['PRICE'], 0.000001);
        self::assertEqualsWithDelta(504.0, (float) $row['PROMO_PRICE'], 0.000001);
        self::assertEquals(1, $row['IS_PROMO']);
    }

    public function testABundleWithoutDiscountIsNotOnPromotion(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->service, discount: '0');

        $row = $this->firstRow('product', ['id' => $example['bundle']->getId()]);

        self::assertEqualsWithDelta(560.0, (float) $row['PRICE'], 0.000001);
        self::assertEquals(0, $row['IS_PROMO']);
        self::assertEqualsWithDelta(560.0, (float) $row['BEST_PRICE'], 0.000001);
    }

    public function testTheFixedPriceOfABundleIsTheCatalogPriceOfItsSaleElement(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->service, BundlePriceMode::Fixed);

        $row = $this->firstRow('product', ['id' => $example['bundle']->getId()]);

        self::assertEqualsWithDelta(999.0, (float) $row['PRICE'], 0.000001);
        self::assertEquals(0, $row['IS_PROMO']);
    }

    public function testASimpleProductIsStrictlyUnchanged(): void
    {
        $factory = $this->createFixtureFactory();
        $this->exampleBundle($factory, $this->service);
        $simple = $this->component($factory, '12.5', 3);
        $simple->setLocale(LangQuery::create()->filterByByDefault(1)->findOne()->getLocale())->setTitle('Simple')->save();

        $row = $this->firstRow('product', ['id' => $simple->getId()]);

        self::assertEqualsWithDelta(12.5, (float) $row['PRICE'], 0.000001);
        self::assertEquals(0, $row['IS_PROMO']);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function firstRow(string $loop, array $arguments): array
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne() ?? LangQuery::create()->findOne();
        $result = $this->getService(LoopExecutor::class)->execute($loop, $arguments + [
            'lang' => $lang->getId(),
            'currency' => Currency::getDefaultCurrency()->getId(),
            'visible' => '*',
        ]);

        foreach ($result as $row) {
            return $row->getVarVal();
        }

        self::fail('The loop answered no row.');
    }
}
