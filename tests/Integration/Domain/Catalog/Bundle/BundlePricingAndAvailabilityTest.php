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

namespace Thelia\Tests\Integration\Domain\Catalog\Bundle;

use Propel\Runtime\Propel;
use Thelia\Domain\Catalog\Bundle\BundleAvailability;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\BundleCompositionResolver;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundleDiscountType;
use Thelia\Domain\Catalog\Bundle\BundlePriceCalculator;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Model\Currency;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\RecordsStatements;

/**
 * The figures of the plan: bundle of a bike, two helmets and up to three locks.
 */
final class BundlePricingAndAvailabilityTest extends ActionIntegrationTestCase
{
    use BuildsBundles;
    use RecordsStatements;

    private BundleCompositionService $service;

    private BundlePriceCalculator $calculator;

    private BundleAvailability $availability;

    private BundleCompositionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(BundleCompositionService::class);
        $this->calculator = $this->getService(BundlePriceCalculator::class);
        $this->availability = $this->getService(BundleAvailability::class);
        $this->resolver = $this->getService(BundleCompositionResolver::class);
    }

    public function testAPercentDiscountOnTheChosenComposition(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $composition = BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => 1],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2],
            ['bundleItemId' => $example['lock']->getId(), 'quantity' => 2],
        ]);

        $price = $this->calculator->forCompositions([[(int) $example['bundle']->getId(), $this->resolver->resolve((int) $example['bundle']->getId(), $composition)]], Currency::getDefaultCurrency())[0] ?? null;

        self::assertNotNull($price);
        self::assertSame('600.000000', $price->untaxedFullPrice);
        self::assertSame('540.000000', $price->untaxedPrice);
        self::assertTrue($price->hasDiscount());
    }

    public function testAnAmountDiscountOnTheChosenComposition(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service, discount: '50', type: BundleDiscountType::Amount);
        $composition = BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => 1],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2],
            ['bundleItemId' => $example['lock']->getId(), 'quantity' => 2],
        ]);

        $price = $this->calculator->forCompositions([[(int) $example['bundle']->getId(), $composition]], Currency::getDefaultCurrency())[0] ?? null;

        self::assertNotNull($price);
        self::assertSame('600.000000', $price->untaxedFullPrice);
        self::assertSame('550.000000', $price->untaxedPrice);
    }

    public function testTheDefaultCompositionLeavesTheOptionalLockOut(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);

        $prices = $this->calculator->forBundles([(int) $example['bundle']->getId()], Currency::getDefaultCurrency());

        // 500 + 2 x 30 = 560, less 10 %
        self::assertSame('560.000000', $prices[(int) $example['bundle']->getId()]->untaxedFullPrice);
        self::assertSame('504.000000', $prices[(int) $example['bundle']->getId()]->untaxedPrice);
    }

    public function testADiscountAboveThePriceFloorsTheBundleAtZero(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service, discount: '9999', type: BundleDiscountType::Amount);

        $price = $this->calculator->forBundles([(int) $example['bundle']->getId()], Currency::getDefaultCurrency())[(int) $example['bundle']->getId()];

        self::assertSame('560.000000', $price->untaxedFullPrice);
        self::assertSame('0.000000', $price->untaxedPrice);
    }

    public function testAFixedPriceBundleIsNotComputed(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service, BundlePriceMode::Fixed);
        $bundleId = (int) $example['bundle']->getId();

        self::assertSame([], $this->calculator->forBundles([$bundleId], Currency::getDefaultCurrency()));
        self::assertNull($this->calculator->forCompositions([[$bundleId, BundleComposition::fromArray([])]], Currency::getDefaultCurrency())[0] ?? null);
    }

    public function testThePriceOfAComponentOnPromotionIsItsPromoPrice(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $bikeSaleElements = $this->saleElementsOf($example['bikeProduct']);
        $bikeSaleElements->setPromo(1)->save();
        $price = \Thelia\Model\ProductPriceQuery::create()->filterByProductSaleElementsId($bikeSaleElements->getId())->findOne();
        $price->setPromoPrice('400')->save();

        $prices = $this->calculator->forBundles([(int) $example['bundle']->getId()], Currency::getDefaultCurrency());

        // 400 + 2 x 30 = 460
        self::assertSame('460.000000', $prices[(int) $example['bundle']->getId()]->untaxedFullPrice);
    }

    public function testAnOrphanedBundleLeftOutOfAListingNamesNoComponent(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $bundleId = (int) $example['bundle']->getId();
        $example['helmetProduct']->delete();

        $prices = [];
        $statements = $this->statementsDuring(function () use ($bundleId, &$prices): void {
            $prices = $this->calculator->forBundles([$bundleId], Currency::getDefaultCurrency());
        });

        self::assertSame([], $prices, 'A bundle with an orphaned component has no price to show.');
        self::assertSame([], array_values(array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'ComponentReference'))), 'The reference of the component is read for a refusal that is thrown, not for a bundle left out of a list.');
    }

    public function testTwoBundlesAreCalculatedWithoutAQueryPerBundle(): void
    {
        $first = $this->exampleBundle($this->factory, $this->service);
        $second = $this->exampleBundle($this->factory, $this->service);
        $connection = Propel::getConnection('TheliaMain');
        $connection->useDebug(true);

        $before = $connection->getQueryCount();
        $this->calculator->forBundles([(int) $first['bundle']->getId()], Currency::getDefaultCurrency());
        $queriesForOne = $connection->getQueryCount() - $before;

        $before = $connection->getQueryCount();
        $this->calculator->forBundles([(int) $first['bundle']->getId(), (int) $second['bundle']->getId()], Currency::getDefaultCurrency());
        $queriesForTwo = $connection->getQueryCount() - $before;
        $connection->useDebug(false);

        self::assertGreaterThan(0, $queriesForOne);
        self::assertSame($queriesForOne, $queriesForTwo);
    }

    public function testTheBundleIsAvailableAsOftenAsTheScarcestComponentAllows(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $bundleId = (int) $example['bundle']->getId();

        // Default composition: min(7 / 1, floor(5 / 2)) = 2
        self::assertSame([$bundleId => 2], $this->availability->forBundles([$bundleId]));

        // With two locks: min(7, 2, floor(10 / 2)) = 2
        $withLocks = BundleComposition::fromArray([
            ['bundleItemId' => $example['bike']->getId(), 'quantity' => 1],
            ['bundleItemId' => $example['helmet']->getId(), 'quantity' => 2],
            ['bundleItemId' => $example['lock']->getId(), 'quantity' => 2],
        ]);
        self::assertSame(2, $this->availability->forComposition($bundleId, $withLocks));
    }

    public function testTheStockIsDividedAndRoundedDown(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $this->saleElementsOf($example['helmetProduct'])->setQuantity(7)->save();
        $this->saleElementsOf($example['bikeProduct'])->setQuantity(9)->save();

        // floor(7 / 2) = 3, floor(9 / 1) = 9
        self::assertSame([(int) $example['bundle']->getId() => 3], $this->availability->forBundles([(int) $example['bundle']->getId()]));
    }

    public function testAComponentOutOfStockMakesTheBundleUnavailable(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $this->saleElementsOf($example['helmetProduct'])->setQuantity(1)->save();

        // floor(1 / 2) = 0
        self::assertSame([(int) $example['bundle']->getId() => 0], $this->availability->forBundles([(int) $example['bundle']->getId()]));
    }

    public function testAnOfflineRequiredComponentMakesTheBundleUnavailable(): void
    {
        $example = $this->exampleBundle($this->factory, $this->service);
        $example['bikeProduct']->setVisible(0)->save();

        self::assertSame([(int) $example['bundle']->getId() => 0], $this->availability->forBundles([(int) $example['bundle']->getId()]));
    }

    public function testSeveralBundlesAreReadWithoutAQueryPerBundle(): void
    {
        $first = $this->exampleBundle($this->factory, $this->service);
        $second = $this->exampleBundle($this->factory, $this->service);
        $this->saleElementsOf($second['helmetProduct'])->setQuantity(1)->save();
        $plain = $this->component($this->factory, '10', 3);
        $connection = Propel::getConnection('TheliaMain');
        $connection->useDebug(true);

        $before = $connection->getQueryCount();
        $this->availability->forBundles([(int) $first['bundle']->getId()]);
        $queriesForOne = $connection->getQueryCount() - $before;

        $before = $connection->getQueryCount();
        $availability = $this->availability->forBundles([(int) $first['bundle']->getId(), (int) $second['bundle']->getId(), (int) $plain->getId()]);
        $queriesForThree = $connection->getQueryCount() - $before;
        $connection->useDebug(false);

        self::assertGreaterThan(0, $queriesForOne);
        self::assertSame([(int) $first['bundle']->getId() => 2, (int) $second['bundle']->getId() => 0, (int) $plain->getId() => 0], $availability);
        self::assertSame($queriesForOne, $queriesForThree);
    }
}
