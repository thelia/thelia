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

namespace Thelia\Tests\Integration\Domain\Pricing\Rule\Storage;

use Thelia\Core\Event\Sale\SaleCreateEvent;
use Thelia\Core\Event\Sale\SaleUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Pricing\Rule\Scope\ScopeMaterializer;
use Thelia\Domain\Pricing\Rule\Storage\PublicPriceReader;
use Thelia\Domain\Pricing\Rule\Storage\PublicPriceSegmentWriter;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRulePriceQuery;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Sale;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The stored public price of a sale element: computed on its catalog price, per
 * currency, cut into the dated segments of the rules covering it, and never
 * written into the catalog itself.
 */
final class PublicPriceSegmentWriterTest extends ActionIntegrationTestCase
{
    private PublicPriceSegmentWriter $writer;

    private PublicPriceReader $reader;

    private ScopeMaterializer $materializer;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = $this->getService(PublicPriceSegmentWriter::class);
        $this->reader = $this->getService(PublicPriceReader::class);
        $this->materializer = $this->getService(ScopeMaterializer::class);
        $this->currency = $this->factory->currency();
    }

    public function testARunningRuleStoresOneOpenSegmentReadBackAsTheCurrentPrice(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $rule = $this->runningRuleOn($product, ['percentageValue' => 20.0]);

        $written = $this->writer->recomputeForProductSaleElements([$pse->getId()]);

        self::assertSame($this->visibleCurrencies(), $written, 'one open segment per currency the shop offers');
        $prices = $this->reader->currentPrices([$pse->getId()], $this->currency);
        self::assertArrayHasKey($pse->getId(), $prices);
        // A percentage of the taxed price is the same percentage of the untaxed one.
        self::assertEqualsWithDelta(80.0, $prices[$pse->getId()]->untaxedPrice, 0.000001);
        self::assertSame($rule->getId(), $prices[$pse->getId()]->ruleId);
        self::assertTrue($prices[$pse->getId()]->displayInitialPrice);
        self::assertNull($prices[$pse->getId()]->validUntil);
    }

    /**
     * The parity criterion: a rule and a flash sale with the same offset agree to
     * the cent, because they share the tax arithmetic.
     */
    public function testAnAmountRuleWritesWhatAFlashSaleWithTheSameOffsetWrites(): void
    {
        $saleProduct = $this->catalogProduct();
        $ruleProduct = $this->catalogProduct();

        $flashSalePrice = $this->publicPromoPriceWrittenFor($saleProduct, '25', Sale::OFFSET_TYPE_AMOUNT);

        $rule = $this->runningRuleOn($ruleProduct, ['effectType' => CatalogPriceRule::EFFECT_TYPE_AMOUNT]);
        $this->factory->catalogPriceRuleEffectCurrency($rule, $this->currency, 25.0);
        $pse = $this->defaultPseFor($ruleProduct);

        $this->writer->recomputeForProductSaleElements([$pse->getId()]);

        self::assertEqualsWithDelta($flashSalePrice, $this->reader->currentPrices([$pse->getId()], $this->currency)[$pse->getId()]->untaxedPrice, 0.000001);
    }

    public function testTheCatalogPriceAndTheManualPromoPriceAreLeftUntouched(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        ProductPriceQuery::create()->filterByProductSaleElementsId($pse->getId())->findOne()
            ->setPromoPrice('50.000000')->save();
        $pse->setPromo(1)->save();

        $this->runningRuleOn($product, ['percentageValue' => 20.0]);
        $this->writer->recomputeForProductSaleElements([$pse->getId()]);

        $catalogRow = ProductPriceQuery::create()->filterByProductSaleElementsId($pse->getId())->findOne();
        self::assertSame('100.000000', $catalogRow->getPrice());
        self::assertSame('50.000000', $catalogRow->getPromoPrice(), 'a rule never overwrites a promo price typed by hand');
        // The rule prices from the catalog price, not from the manual promo.
        self::assertEqualsWithDelta(80.0, $this->reader->currentPrices([$pse->getId()], $this->currency)[$pse->getId()]->untaxedPrice, 0.000001);
    }

    /**
     * Recette 1 and 6: the segment carries the window, so the reader answers the
     * catalog price outside it without anything having run in between.
     */
    public function testADatedRuleIsReadOnlyInsideItsWindow(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $this->runningRuleOn($product, [
            'percentageValue' => 20.0,
            'startDate' => new \DateTime('2030-01-01 00:00:00'),
            'endDate' => new \DateTime('2030-02-01 00:00:00'),
        ]);

        $this->writer->recomputeForProductSaleElements([$pse->getId()], new \DateTimeImmutable('2029-12-01'));

        self::assertSame([], $this->reader->currentPrices([$pse->getId()], $this->currency, new \DateTimeImmutable('2029-12-31 23:59:59')));
        self::assertArrayHasKey($pse->getId(), $this->reader->currentPrices([$pse->getId()], $this->currency, new \DateTimeImmutable('2030-01-01 00:00:00')));
        self::assertArrayHasKey($pse->getId(), $this->reader->currentPrices([$pse->getId()], $this->currency, new \DateTimeImmutable('2030-01-31 23:59:59')));
        self::assertSame([], $this->reader->currentPrices([$pse->getId()], $this->currency, new \DateTimeImmutable('2030-02-01 00:00:00')));
    }

    public function testASecondaryCurrencyWithoutARowOfItsOwnIsPricedFromTheDefaultCurrency(): void
    {
        $secondary = $this->factory->currency(['code' => 'USD', 'symbol' => '$', 'rate' => 2.0, 'visible' => 1]);
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $this->runningRuleOn($product, ['percentageValue' => 50.0]);

        $this->writer->recomputeForProductSaleElements([$pse->getId()]);

        $inSecondary = $this->reader->currentPrices([$pse->getId()], $secondary);
        self::assertArrayHasKey($pse->getId(), $inSecondary);
        // 100 in the default currency is 200 at a rate of 2, half of which is 100.
        self::assertEqualsWithDelta(100.0 * 2.0 / (float) Currency::getDefaultCurrency()->getRate() * 0.5, $inSecondary[$pse->getId()]->untaxedPrice, 0.000001);
    }

    public function testAnAmountRuleWithoutAValueInACurrencyPricesNothingThere(): void
    {
        $secondary = $this->factory->currency(['code' => 'GBP', 'symbol' => '£', 'rate' => 1.0, 'visible' => 1]);
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $rule = $this->runningRuleOn($product, ['effectType' => CatalogPriceRule::EFFECT_TYPE_AMOUNT]);
        $this->factory->catalogPriceRuleEffectCurrency($rule, $this->currency, 10.0);

        $this->writer->recomputeForProductSaleElements([$pse->getId()]);

        self::assertArrayHasKey($pse->getId(), $this->reader->currentPrices([$pse->getId()], $this->currency));
        self::assertSame([], $this->reader->currentPrices([$pse->getId()], $secondary));
    }

    public function testASaleElementWithoutAnyPriceRowStopsNothing(): void
    {
        $product = $this->catalogProduct();
        $priced = $this->defaultPseFor($product);
        $unpriced = $this->factory->productSaleElement($product);
        $this->runningRuleOn($product, ['percentageValue' => 20.0]);

        $written = $this->writer->recomputeForProductSaleElements([$priced->getId(), $unpriced->getId()]);

        self::assertSame($this->visibleCurrencies(), $written);
        self::assertArrayNotHasKey($unpriced->getId(), $this->reader->currentPrices([$unpriced->getId()], $this->currency));
    }

    public function testAReservedRuleAndATurnedOffRuleStoreNothing(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $this->runningRuleOn($product, ['percentageValue' => 20.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);
        $this->runningRuleOn($product, ['percentageValue' => 30.0, 'active' => false]);

        self::assertSame(0, $this->writer->recomputeForProductSaleElements([$pse->getId()]));
    }

    public function testRecomputingAfterTheRuleWasTurnedOffRemovesTheStoredPrice(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $rule = $this->runningRuleOn($product, ['percentageValue' => 20.0]);
        $this->writer->recomputeForProductSaleElements([$pse->getId()]);
        self::assertSame($this->visibleCurrencies(), CatalogPriceRulePriceQuery::create()->filterByProductSaleElementsId($pse->getId())->count());

        $rule->setActive(false)->save();
        $this->writer->recomputeForProductSaleElements([$pse->getId()]);

        self::assertSame(0, CatalogPriceRulePriceQuery::create()->filterByProductSaleElementsId($pse->getId())->count());
    }

    public function testPurgingDropsTheSegmentsAlreadyOver(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $this->runningRuleOn($product, [
            'percentageValue' => 20.0,
            'startDate' => new \DateTime('2020-01-01 00:00:00'),
            'endDate' => new \DateTime('2020-02-01 00:00:00'),
        ]);
        // Written as of 2019, so the segment is stored although it is over today.
        $this->writer->recomputeForProductSaleElements([$pse->getId()], new \DateTimeImmutable('2019-06-01'));
        self::assertSame($this->visibleCurrencies(), CatalogPriceRulePriceQuery::create()->filterByProductSaleElementsId($pse->getId())->count());

        self::assertSame($this->visibleCurrencies(), $this->writer->purgeExpired());
        self::assertSame(0, CatalogPriceRulePriceQuery::create()->filterByProductSaleElementsId($pse->getId())->count());
    }

    public function testTwoRulesCoveringTheSameSaleElementChainByPriority(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $this->runningRuleOn($product, ['percentageValue' => 10.0, 'priority' => 100]);
        $fixed = $this->runningRuleOn($product, ['effectType' => CatalogPriceRule::EFFECT_TYPE_FIXED_PRICE, 'priority' => 10, 'stopProcessing' => true]);
        $this->factory->catalogPriceRuleEffectCurrency($fixed, $this->currency, 60.0);

        $this->writer->recomputeForProductSaleElements([$pse->getId()]);

        $price = $this->reader->currentPrices([$pse->getId()], $this->currency)[$pse->getId()];
        self::assertSame($fixed->getId(), $price->ruleId);
        self::assertEqualsWithDelta($this->untaxed($product, 60.0), $price->untaxedPrice, 0.000001);
    }

    private function runningRuleOn(Product $product, array $overrides): CatalogPriceRule
    {
        $rule = $this->factory->catalogPriceRule($overrides + ['active' => true]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $this->materializer->materializeRule($rule);

        return $rule;
    }

    private function untaxed(Product $product, float $taxed): float
    {
        return (float) $this->getService(\Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface::class)
            ->createTaxCalculator()
            ->load($product, \Thelia\Model\Country::getShopLocation())
            ->getUntaxedPrice($taxed);
    }

    private function publicPromoPriceWrittenFor(Product $product, string $offset, int $offsetType): float
    {
        $sale = $this->dispatch(
            (new SaleCreateEvent())->setLocale('en_US')->setTitle('Public')->setSaleLabel('PUB'),
            TheliaEvents::SALE_CREATE,
        )->getSale();

        $event = new SaleUpdateEvent($sale->getId());
        $event
            ->setLocale('en_US')
            ->setTitle('Public')
            ->setSaleLabel('PUB')
            ->setActive(true)
            ->setStartDate(new \DateTime('-1 day'))
            ->setEndDate(new \DateTime('+1 day'))
            ->setPriceOffsetType($offsetType)
            ->setDisplayInitialPrice(true)
            ->setChapo('')
            ->setDescription('')
            ->setPostscriptum('')
            ->setPriceOffsets([$this->currency->getId() => $offset])
            ->setProducts([$product->getId()])
            ->setProductAttributes([]);

        $this->dispatch($event, TheliaEvents::SALE_UPDATE);

        return (float) ProductPriceQuery::create()
            ->filterByProductSaleElementsId($this->defaultPseFor($product)->getId())
            ->filterByCurrencyId($this->currency->getId())
            ->findOne()
            ->getPromoPrice();
    }

    private function visibleCurrencies(): int
    {
        return \Thelia\Model\CurrencyQuery::create()->filterByVisible(1)->count();
    }

    private function catalogProduct(): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => 100.0],
        );
    }

    private function defaultPseFor(Product $product): ProductSaleElements
    {
        $pse = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(true)->findOne();
        self::assertNotNull($pse);

        return $pse;
    }
}
