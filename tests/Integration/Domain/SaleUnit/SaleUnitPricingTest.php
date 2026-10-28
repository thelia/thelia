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

namespace Thelia\Tests\Integration\Domain\SaleUnit;

use Thelia\Domain\Pricing\EffectivePrice;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Domain\SaleUnit\Pricing\SaleUnitPricingActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Currency;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * What a sale unit's own price does to the effective price of a sale element:
 * it replaces it for that unit, leaves every other unit and the national shop
 * untouched, and steps aside for the national price on the sale elements the
 * unit never overrode — the fallback the feature stands on.
 */
final class SaleUnitPricingTest extends IntegrationTestCase
{
    private EffectivePriceCatalog $effectivePriceCatalog;

    private SaleUnitContext $saleUnitContext;

    private FixtureFactory $fixtures;

    private Currency $currency;

    private ProductSaleElements $productSaleElements;

    protected function setUp(): void
    {
        parent::setUp();

        $this->effectivePriceCatalog = $this->getService(EffectivePriceCatalog::class);
        $this->saleUnitContext = $this->getService(SaleUnitContext::class);

        $this->fixtures = $this->createFixtureFactory();
        $this->currency = $this->fixtures->currency();
        $product = $this->fixtures->product(
            $this->fixtures->category(),
            $this->fixtures->taxRule(),
            $this->currency,
            ['basePrice' => 100.0],
        );
        $this->productSaleElements = $product->getDefaultSaleElements();
    }

    protected function tearDown(): void
    {
        // These services memoise per request and are reset by the request lifecycle
        // in production. A test that populates them directly, with no request to reset
        // them, would otherwise leak into the next test — a stale "a rule is active"
        // or "this unit prices" turning a later query-count assertion red.
        $this->getService(SaleUnitContext::class)->reset();
        $this->getService(SaleUnitActivityChecker::class)->reset();
        $this->getService(SaleUnitPricingActivityChecker::class)->reset();
        $this->getService('services_resetter')->reset();
        $this->getService(EffectivePriceCatalog::class)->reset();

        parent::tearDown();
    }

    public function testTheUnitPriceReplacesTheEffectivePriceOfItsUnit(): void
    {
        $saleUnit = $this->fixtures->saleUnit();
        $this->fixtures->saleUnitProductPrice($saleUnit, $this->productSaleElements, $this->currency, 79.9);

        $this->saleUnitContext->setSaleUnit($saleUnit);

        $price = $this->effectivePriceCatalog->priceFor($this->productSaleElements->getId(), $this->currency, null);

        self::assertNotNull($price);
        self::assertEqualsWithDelta(79.9, $price->untaxedPromoPrice, 0.0001);
        self::assertSame(EffectivePrice::SOURCE_SALE_UNIT, $price->source);
        self::assertSame($saleUnit->getId(), $price->saleUnitId);
        self::assertFalse($price->displayInitialPrice, 'a unit price is the price, nothing is struck through');
    }

    public function testASaleElementTheUnitNeverPricedKeepsTheNationalPrice(): void
    {
        $saleUnit = $this->fixtures->saleUnit();
        $this->saleUnitContext->setSaleUnit($saleUnit);

        $price = $this->effectivePriceCatalog->priceFor($this->productSaleElements->getId(), $this->currency, null);

        self::assertNull($price, 'no override: the catalog price stands, exactly as without the feature');
    }

    public function testTheUnitPriceOfOneUnitLeaksNowhereElse(): void
    {
        $pricingUnit = $this->fixtures->saleUnit();
        $otherUnit = $this->fixtures->saleUnit();
        $this->fixtures->saleUnitProductPrice($pricingUnit, $this->productSaleElements, $this->currency, 79.9);

        $this->saleUnitContext->setSaleUnit($otherUnit);
        self::assertNull(
            $this->effectivePriceCatalog->priceFor($this->productSaleElements->getId(), $this->currency, null),
            'another unit sells at the national price',
        );

        $this->saleUnitContext->setSaleUnit(null);
        self::assertNull(
            $this->effectivePriceCatalog->priceFor($this->productSaleElements->getId(), $this->currency, null),
            'outside any unit the national price stands',
        );
    }

    public function testTheUnitPriceOutranksACatalogPriceRule(): void
    {
        $saleUnit = $this->fixtures->saleUnit();
        $this->fixtures->saleUnitProductPrice($saleUnit, $this->productSaleElements, $this->currency, 79.9);

        $rule = $this->fixtures->catalogPriceRule([
            'active' => true,
            'effectType' => CatalogPriceRule::EFFECT_TYPE_PERCENTAGE,
            'percentageValue' => 50.0,
        ]);
        $this->fixtures->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $this->productSaleElements->getProductId());
        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        $this->saleUnitContext->setSaleUnit($saleUnit);

        $price = $this->effectivePriceCatalog->priceFor($this->productSaleElements->getId(), $this->currency, null);

        self::assertNotNull($price);
        self::assertSame(EffectivePrice::SOURCE_SALE_UNIT, $price->source);
        self::assertEqualsWithDelta(79.9, $price->untaxedPromoPrice, 0.0001, 'the unit price is the price; a national rule computed on the national base has nothing to say');
    }

    public function testTheUnitPromoPriceAppliesWhenTheSaleElementIsInPromo(): void
    {
        $saleUnit = $this->fixtures->saleUnit();
        $this->fixtures->saleUnitProductPrice($saleUnit, $this->productSaleElements, $this->currency, 79.9, 59.9);

        $this->productSaleElements->setPromo(1)->save();
        $this->saleUnitContext->setSaleUnit($saleUnit);

        $price = $this->effectivePriceCatalog->priceFor($this->productSaleElements->getId(), $this->currency, null);

        self::assertNotNull($price);
        self::assertEqualsWithDelta(59.9, $price->untaxedPromoPrice, 0.0001, 'the promo flag of the sale element picks the promo column of the unit row, like it picks it on product_price');
    }
}
