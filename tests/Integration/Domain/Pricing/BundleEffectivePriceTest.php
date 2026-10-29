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

namespace Thelia\Tests\Integration\Domain\Pricing;

use Thelia\Api\Service\DataAccess\ProductSaleElementsAccessService;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundlePriceMode;
use Thelia\Domain\Pricing\EffectivePrice;
use Thelia\Domain\Pricing\EffectivePriceResolver;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Model\Currency;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;

/**
 * A bundle priced by its components says its price through the effective price, the one place
 * the readers look at: 560 struck and 504 once 10 % are off for the default composition of the plan.
 */
final class BundleEffectivePriceTest extends IntegrationTestCase
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

    public function testTheResolverPricesTheSaleElementOfAComputedBundle(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->service);
        $saleElementsId = (int) $this->saleElementsOf($example['bundle'])->getId();

        $price = $this->getService(EffectivePriceResolver::class)->resolve([$saleElementsId], Currency::getDefaultCurrency(), null)[$saleElementsId] ?? null;

        self::assertNotNull($price);
        self::assertSame(EffectivePrice::SOURCE_BUNDLE, $price->source);
        self::assertSame('560.000000', $price->untaxedPrice);
        self::assertSame(504.0, $price->untaxedPromoPrice);
        self::assertTrue($price->displayInitialPrice);
    }

    public function testTheCatalogPromotionOfTheSaleElementOfTheBundleIsNotThePriceOfTheBundle(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->service);
        $saleElements = $this->saleElementsOf($example['bundle']);
        $saleElements->setPromo(1)->save();

        $price = $this->getService(EffectivePriceResolver::class)->resolve([(int) $saleElements->getId()], Currency::getDefaultCurrency(), null)[(int) $saleElements->getId()];

        self::assertSame(EffectivePrice::SOURCE_BUNDLE, $price->source);
        self::assertSame(504.0, $price->untaxedPromoPrice);
    }

    public function testTheSaleElementOfAFixedBundleIsLeftToTheCatalog(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->service, BundlePriceMode::Fixed);
        $saleElementsId = (int) $this->saleElementsOf($example['bundle'])->getId();

        self::assertArrayNotHasKey($saleElementsId, $this->getService(EffectivePriceResolver::class)->resolve([$saleElementsId], Currency::getDefaultCurrency(), null));
    }

    public function testASimpleProductIsLeftToTheCatalog(): void
    {
        $factory = $this->createFixtureFactory();
        $this->exampleBundle($factory, $this->service);
        $simple = $this->component($factory, '12.5', 3);
        $saleElementsId = (int) $this->saleElementsOf($simple)->getId();

        self::assertSame([], $this->getService(EffectivePriceResolver::class)->resolve([$saleElementsId], Currency::getDefaultCurrency(), null));
    }

    public function testTheProductPageReadsThePriceOfTheComponentsAndTheStockTheyAllow(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->service);

        $saleElements = json_decode(
            (string) $this->getService(ProductSaleElementsAccessService::class)->psesByProduct($example['bundle']->getId()),
            true,
            flags: \JSON_THROW_ON_ERROR,
        )[0];

        self::assertTrue($saleElements['isPromo']);
        self::assertEqualsWithDelta(560.0, (float) $saleElements['untaxedPrice'], 0.000001);
        self::assertEqualsWithDelta(504.0, (float) $saleElements['promoUntaxedPrice'], 0.000001);
        // min(7, floor(5 / 2)) = 2 for the default composition, whatever the stock of the sale element of the bundle.
        self::assertSame(2, $saleElements['quantity']);
    }

    public function testTheProductPageOfAFixedBundleReadsItsOwnPrice(): void
    {
        $factory = $this->createFixtureFactory();
        $example = $this->exampleBundle($factory, $this->service, BundlePriceMode::Fixed);

        $saleElements = json_decode(
            (string) $this->getService(ProductSaleElementsAccessService::class)->psesByProduct($example['bundle']->getId()),
            true,
            flags: \JSON_THROW_ON_ERROR,
        )[0];

        self::assertFalse($saleElements['isPromo']);
        self::assertEqualsWithDelta(999.0, (float) $saleElements['untaxedPrice'], 0.000001);
    }

    public function testTheProductPageOfASimpleProductReadsItsOwnStock(): void
    {
        $factory = $this->createFixtureFactory();
        $this->exampleBundle($factory, $this->service);
        $simple = $this->component($factory, '12.5', 3);

        $saleElements = json_decode(
            (string) $this->getService(ProductSaleElementsAccessService::class)->psesByProduct($simple->getId()),
            true,
            flags: \JSON_THROW_ON_ERROR,
        )[0];

        self::assertSame(3, (int) $saleElements['quantity']);
        self::assertEqualsWithDelta(12.5, (float) $saleElements['untaxedPrice'], 0.000001);
    }
}
