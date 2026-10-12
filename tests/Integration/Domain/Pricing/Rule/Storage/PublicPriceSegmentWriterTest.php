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

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Thelia\Core\Event\Sale\SaleCreateEvent;
use Thelia\Core\Event\Sale\SaleUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Pricing\Rule\Engine\PriceSegmentBuilder;
use Thelia\Domain\Pricing\Rule\Scope\ScopeMaterializer;
use Thelia\Domain\Pricing\Rule\Storage\BasePriceLoader;
use Thelia\Domain\Pricing\Rule\Storage\PublicPriceReader;
use Thelia\Domain\Pricing\Rule\Storage\PublicPriceSegmentWriter;
use Thelia\Domain\Pricing\Rule\Storage\RuleEffectFactory;
use Thelia\Domain\Pricing\Rule\Storage\ScopeReader;
use Thelia\Domain\Pricing\Rule\Storage\TaxCalculatorLoader;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleCriterionQuery;
use Thelia\Model\CatalogPriceRulePriceQuery;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Map\CatalogPriceRuleTableMap;
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

    public function testRecomputingNoSaleElementWritesNothing(): void
    {
        self::assertSame(0, $this->writer->recomputeForProductSaleElements([]));
    }

    public function testTheWriterClosesTheTransactionItOpens(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $this->runningRuleOn($product, ['percentageValue' => 20.0]);
        $connection = Propel::getWriteConnection(CatalogPriceRuleTableMap::DATABASE_NAME);
        self::assertInstanceOf(ConnectionWrapper::class, $connection);
        $levels = $connection->getNestedTransactionCount();

        $this->writer->recomputeForProductSaleElements([$pse->getId()]);

        self::assertSame($levels, $connection->getNestedTransactionCount());
    }

    public function testAnIdThatIsNotAnIntegerNeverWidensTheDelete(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $this->runningRuleOn($product, ['percentageValue' => 20.0]);
        $this->writer->recomputeForProductSaleElements([$pse->getId()]);

        self::assertSame(0, $this->writer->recomputeForProductSaleElements(['0) OR (1 = 1']));

        self::assertSame($this->visibleCurrencies(), $this->storedPricesOf($pse));
    }

    public function testACurrencyWhereNoRuleHasAValueDoesNotStopTheCurrenciesAfterIt(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $lastCurrency = $this->factory->currency(['code' => 'XTS', 'symbol' => 'X', 'rate' => 1.0, 'visible' => 1]);
        $rule = $this->runningRuleOn($product, ['effectType' => CatalogPriceRule::EFFECT_TYPE_AMOUNT]);
        $this->factory->catalogPriceRuleEffectCurrency($rule, $lastCurrency, 10.0);
        // The writer walks the visible currencies in the order the database answers
        // them, with no ORDER BY: the currencies without a value only come before
        // the one with a value if that order is the order of creation. Checked
        // here, so the test says so the day it stops holding.
        $visibleIds = CurrencyQuery::create()->filterByVisible(1)->find()->getColumnValues('Id');
        self::assertGreaterThan(1, \count($visibleIds));
        self::assertSame($lastCurrency->getId(), end($visibleIds), 'the currency with a value is walked last');

        self::assertSame(1, $this->writer->recomputeForProductSaleElements([$pse->getId()]));

        self::assertArrayHasKey($pse->getId(), $this->reader->currentPrices([$pse->getId()], $lastCurrency));
    }

    /**
     * The writer walks the sale elements in the order the database answers them,
     * with no ORDER BY: the unpriced one is created between two priced ones, so it
     * sits in the middle whether that order is ascending or descending.
     */
    public function testASaleElementWithoutABasePriceDoesNotStopTheOtherSaleElements(): void
    {
        $first = $this->catalogProduct();
        $unpriced = $this->factory->productSaleElement($first);
        $second = $this->catalogProduct();
        $rule = $this->factory->catalogPriceRule(['percentageValue' => 20.0, 'active' => true]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $first->getId());
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $second->getId());
        $this->materializer->materializeRule($rule);
        $firstPse = $this->defaultPseFor($first);
        $secondPse = $this->defaultPseFor($second);

        $written = $this->writer->recomputeForProductSaleElements([$firstPse->getId(), $unpriced->getId(), $secondPse->getId()]);

        self::assertSame(2 * $this->visibleCurrencies(), $written);
        self::assertSame($this->visibleCurrencies(), $this->storedPricesOf($firstPse));
        self::assertSame($this->visibleCurrencies(), $this->storedPricesOf($secondPse));
        self::assertSame(0, $this->storedPricesOf($unpriced));
    }

    public function testAPriceTakenBelowZeroIsFlooredAtZeroAndReported(): void
    {
        $log = new TestHandler();
        $writer = $this->writerLoggingTo(new Logger('test', [$log]));
        $flooredProduct = $this->catalogProduct();
        $floored = $this->defaultPseFor($flooredProduct);
        $amountRule = $this->runningRuleOn($flooredProduct, ['effectType' => CatalogPriceRule::EFFECT_TYPE_AMOUNT]);
        $this->factory->catalogPriceRuleEffectCurrency($amountRule, $this->currency, 1000.0);
        $keptProduct = $this->catalogProduct();
        $kept = $this->defaultPseFor($keptProduct);
        $this->runningRuleOn($keptProduct, ['percentageValue' => 20.0]);

        $writer->recomputeForProductSaleElements([$floored->getId(), $kept->getId()]);

        self::assertEqualsWithDelta(0.0, $this->reader->currentPrices([$floored->getId()], $this->currency)[$floored->getId()]->untaxedPrice, 0.000001);
        $records = $log->getRecords();
        self::assertCount(1, $records, 'only the price taken below zero is reported');
        self::assertSame(Level::Warning, $records[0]->level);
        self::assertSame([
            'product_sale_elements_id' => $floored->getId(),
            'currency_id' => $this->currency->getId(),
            'rule_ids' => [$amountRule->getId()],
        ], $records[0]->context);
    }

    public function testAFailureWhileComputingWritesNothingAndClosesTheTransaction(): void
    {
        $writer = $this->writerLoggingTo(new class extends AbstractLogger {
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                throw new \RuntimeException('The log is unavailable.');
            }
        });
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $rule = $this->runningRuleOn($product, ['effectType' => CatalogPriceRule::EFFECT_TYPE_AMOUNT]);
        $this->factory->catalogPriceRuleEffectCurrency($rule, $this->currency, 1000.0);
        $connection = Propel::getWriteConnection(CatalogPriceRuleTableMap::DATABASE_NAME);
        self::assertInstanceOf(ConnectionWrapper::class, $connection);
        $levels = $connection->getNestedTransactionCount();

        $thrown = null;

        try {
            $writer->recomputeForProductSaleElements([$pse->getId()]);
        } catch (\Throwable $throwable) {
            $thrown = $throwable;
        }

        self::assertInstanceOf(\RuntimeException::class, $thrown, 'the failure is passed on to the caller');
        self::assertSame('The log is unavailable.', $thrown->getMessage());

        self::assertSame($levels, $connection->getNestedTransactionCount());
        self::assertSame(0, $this->storedPricesOf($pse));
    }

    public function testRecomputingEverythingRewritesTheCoveredSaleElementsAndClearsTheOnesNoRuleCoversAnyMore(): void
    {
        $alreadyPriced = $this->defaultPseFor($this->catalogProduct());
        $notYetPriced = $this->defaultPseFor($this->catalogProduct());
        $rule = $this->factory->catalogPriceRule(['percentageValue' => 20.0, 'active' => true]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $alreadyPriced->getProductId());
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $notYetPriced->getProductId());
        $this->materializer->materializeRule($rule);
        $this->writer->recomputeForProductSaleElements([$alreadyPriced->getId()]);

        $leftBehind = [$this->defaultPseFor($this->catalogProduct()), $this->defaultPseFor($this->catalogProduct())];
        $movedRule = $this->factory->catalogPriceRule(['percentageValue' => 30.0, 'active' => true]);

        foreach ($leftBehind as $pse) {
            $this->factory->catalogPriceRuleCriterion($movedRule, CatalogPriceRule::CRITERION_PRODUCT, $pse->getProductId());
        }

        $this->materializer->materializeRule($movedRule);
        $this->writer->recomputeForProductSaleElements([$leftBehind[0]->getId(), $leftBehind[1]->getId()]);
        // The rule now targets a category with nothing in it: the prices stored for
        // its former scope are only found by reading the stored prices themselves.
        CatalogPriceRuleCriterionQuery::create()->filterByCatalogPriceRuleId($movedRule->getId())->delete();
        $this->factory->catalogPriceRuleCriterion($movedRule, CatalogPriceRule::CRITERION_CATEGORY, $this->factory->category()->getId());
        $this->materializer->materializeRule($movedRule);

        $written = $this->writer->recomputeAll(1);

        // Whatever else the table holds was rewritten too: what is left in it is
        // exactly what the run wrote, each sale element counted once.
        self::assertSame(CatalogPriceRulePriceQuery::create()->count(), $written);

        self::assertSame($this->visibleCurrencies(), $this->storedPricesOf($alreadyPriced));
        self::assertSame($this->visibleCurrencies(), $this->storedPricesOf($notYetPriced));
        self::assertSame(0, $this->storedPricesOf($leftBehind[0]));
        self::assertSame(0, $this->storedPricesOf($leftBehind[1]));
    }

    public function testAChunkSizeBelowOneStillRecomputesEverything(): void
    {
        $product = $this->catalogProduct();
        $this->runningRuleOn($product, ['percentageValue' => 20.0]);

        $written = $this->writer->recomputeAll(0);

        self::assertSame(CatalogPriceRulePriceQuery::create()->count(), $written);
        self::assertSame($this->visibleCurrencies(), $this->storedPricesOf($this->defaultPseFor($product)));
    }

    public function testPurgingAsOfAnInstantKeepsTheSegmentsStillRunningThen(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $this->runningRuleOn($product, [
            'percentageValue' => 20.0,
            'startDate' => new \DateTime('2020-01-01 00:00:00'),
            'endDate' => new \DateTime('2020-02-01 00:00:00'),
        ]);
        $justBeforeTheEnd = new \DateTimeImmutable('2020-01-31 23:59:59');
        $atTheEnd = new \DateTimeImmutable('2020-02-01 00:00:00');
        $overJustBeforeTheEnd = $this->segmentsOverAt($justBeforeTheEnd);
        $overAtTheEnd = $this->segmentsOverAt($atTheEnd);
        $this->writer->recomputeForProductSaleElements([$pse->getId()], new \DateTimeImmutable('2019-06-01'));

        self::assertSame($overJustBeforeTheEnd, $this->writer->purgeExpired($justBeforeTheEnd));
        self::assertSame($this->visibleCurrencies(), $this->storedPricesOf($pse));
        self::assertSame($overAtTheEnd - $overJustBeforeTheEnd + $this->visibleCurrencies(), $this->writer->purgeExpired($atTheEnd));
        self::assertSame(0, $this->storedPricesOf($pse));
    }

    private function writerLoggingTo(LoggerInterface $logger): PublicPriceSegmentWriter
    {
        return new PublicPriceSegmentWriter(
            $this->getService(ScopeReader::class),
            $this->getService(BasePriceLoader::class),
            $this->getService(TaxCalculatorLoader::class),
            $this->getService(RuleEffectFactory::class),
            $this->getService(PriceSegmentBuilder::class),
            $logger,
        );
    }

    private function segmentsOverAt(\DateTimeImmutable $instant): int
    {
        return CatalogPriceRulePriceQuery::create()->filterByValidUntil($instant, Criteria::LESS_EQUAL)->count();
    }

    private function storedPricesOf(ProductSaleElements $pse): int
    {
        return CatalogPriceRulePriceQuery::create()->filterByProductSaleElementsId($pse->getId())->count();
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
        return CurrencyQuery::create()->filterByVisible(1)->count();
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
