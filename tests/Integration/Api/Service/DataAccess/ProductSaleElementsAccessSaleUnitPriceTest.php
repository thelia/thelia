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

namespace Thelia\Tests\Integration\Api\Service\DataAccess;

use Thelia\Api\Service\DataAccess\ProductSaleElementsAccessService;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Domain\SaleUnit\Pricing\SaleUnitPricingActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Test\IntegrationTestCase;

/**
 * The product page reads its sale elements through the access service: the price of a
 * sale unit is the price of the sale element there and is never presented as a promotion,
 * while a catalog price rule says on its own whether the catalog price is struck through.
 */
final class ProductSaleElementsAccessSaleUnitPriceTest extends IntegrationTestCase
{
    private Currency $currency;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->currency = $factory->currency();
        $this->product = $factory->product($factory->category(), $factory->taxRule(), $this->currency, ['basePrice' => 100.0, 'baseQuantity' => 100]);
    }

    protected function tearDown(): void
    {
        $this->getService(SaleUnitContext::class)->reset();
        $this->getService(SaleUnitActivityChecker::class)->reset();
        $this->getService(SaleUnitPricingActivityChecker::class)->reset();
        $this->getService('services_resetter')->reset();
        $this->getService(EffectivePriceCatalog::class)->reset();

        parent::tearDown();
    }

    public function testTheUnitPriceIsTheChargedPriceAndIsNotAPromotion(): void
    {
        $factory = $this->createFixtureFactory();
        $saleUnit = $factory->saleUnit();
        $factory->saleUnitProductPrice($saleUnit, $this->product->getDefaultSaleElements(), $this->currency, 79.9);
        $this->getService(SaleUnitContext::class)->setSaleUnit($saleUnit);

        $saleElement = $this->firstSaleElement();

        self::assertFalse($saleElement['isPromo']);
        self::assertFalse($saleElement['displayInitialPrice']);
        self::assertEqualsWithDelta(79.9, (float) $saleElement['untaxedPrice'], 0.000001);
        self::assertEqualsWithDelta(79.9, (float) $saleElement['promoUntaxedPrice'], 0.000001);
    }

    public function testASaleElementTheUnitNeverPricedAnswersNoInitialPriceVerdict(): void
    {
        $this->getService(SaleUnitContext::class)->setSaleUnit($this->createFixtureFactory()->saleUnit());

        $saleElement = $this->firstSaleElement();

        self::assertFalse($saleElement['isPromo']);
        self::assertNull($saleElement['displayInitialPrice']);
        self::assertEqualsWithDelta(100.0, (float) $saleElement['untaxedPrice'], 0.000001);
    }

    public function testARuleThatShowsTheCatalogPriceKeepsItPromoAndSaysSo(): void
    {
        $factory = $this->createFixtureFactory();
        $rule = $factory->catalogPriceRule(['active' => true, 'percentageValue' => 20.0, 'displayInitialPrice' => true]);
        $factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $this->product->getId());
        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        $saleElement = $this->firstSaleElement();

        self::assertTrue($saleElement['isPromo']);
        self::assertTrue($saleElement['displayInitialPrice']);
        self::assertEqualsWithDelta(80.0, (float) $saleElement['promoUntaxedPrice'], 0.000001);
    }

    private function firstSaleElement(): array
    {
        $saleElements = json_decode(
            (string) $this->getService(ProductSaleElementsAccessService::class)->psesByProduct($this->product->getId()),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );

        self::assertNotSame([], $saleElements);

        return $saleElements[0];
    }
}
