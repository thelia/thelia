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

namespace Thelia\Tests\Integration\Domain\Pricing\Rule\Preview;

use Thelia\Core\Event\CatalogPriceRule\CatalogPriceRuleCreateEvent;
use Thelia\Domain\Pricing\Rule\Preview\RulePreviewService;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * Recette 4 of the ticket: before it is turned on, a rule shows what it covers and
 * the price before and after on a sample - without writing anything.
 */
final class RulePreviewServiceTest extends ActionIntegrationTestCase
{
    private RulePreviewService $preview;

    private Currency $currency;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preview = $this->getService(RulePreviewService::class);
        $this->currency = $this->factory->currency();
        $this->category = $this->factory->category();
    }

    public function testThePreviewCountsTheScopeAndPricesASampleWithoutWritingAnything(): void
    {
        $inScope = $this->catalogProduct($this->category);
        $this->catalogProduct($this->category);
        $this->catalogProduct($this->factory->category());
        $rulesBefore = CatalogPriceRuleQuery::create()->count();

        $preview = $this->preview->preview($this->definition([
            'criteria' => [CatalogPriceRule::CRITERION_CATEGORY => [$this->category->getId()]],
        ]), $this->currency);

        self::assertSame(2, $preview->affectedProductCount);
        self::assertSame(2, $preview->affectedSaleElementCount);
        self::assertCount(2, $preview->lines);
        self::assertSame($inScope->getId(), $preview->lines[0]->productId);
        self::assertEqualsWithDelta(100.0, $preview->lines[0]->untaxedBefore, 0.000001);
        self::assertEqualsWithDelta(80.0, $preview->lines[0]->untaxedAfter, 0.000001);
        self::assertTrue($preview->lines[0]->changedByTheRule);
        self::assertGreaterThan($preview->lines[0]->untaxedAfter, $preview->lines[0]->taxedAfter);
        self::assertSame($rulesBefore, CatalogPriceRuleQuery::create()->count(), 'nothing was stored');
    }

    public function testEveryLineNamesTheSaleElementItPrices(): void
    {
        $first = $this->catalogProduct($this->category);
        $second = $this->catalogProduct($this->category);

        $preview = $this->preview->preview($this->definition([
            'criteria' => [CatalogPriceRule::CRITERION_CATEGORY => [$this->category->getId()]],
        ]), $this->currency);

        self::assertCount(2, $preview->lines);
        self::assertSame($this->defaultSaleElementRef($first), $preview->lines[0]->reference);
        self::assertSame($this->defaultSaleElementRef($second), $preview->lines[1]->reference);
    }

    public function testThePreviewChainsWithTheRulesAlreadyRunning(): void
    {
        $product = $this->catalogProduct($this->category);
        $running = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0, 'priority' => 100]);
        $this->factory->catalogPriceRuleCriterion($running, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $this->getService(RuleRepricer::class)->afterRuleChanged($running);

        $preview = $this->preview->preview($this->definition([
            'percentageValue' => 50.0,
            'priority' => 10,
            'stopProcessing' => true,
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), $this->currency);

        $line = $preview->lines[0];
        self::assertEqualsWithDelta(90.0, $line->untaxedBefore, 0.000001, 'what the running rule gives today');
        self::assertEqualsWithDelta(50.0, $line->untaxedAfter, 0.000001, 'the reviewed rule comes first and stops the chain');
    }

    public function testEditingARuleLeavesItsCurrentVersionOutOfTheChain(): void
    {
        $product = $this->catalogProduct($this->category);
        $edited = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0]);
        $this->factory->catalogPriceRuleCriterion($edited, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $this->getService(RuleRepricer::class)->afterRuleChanged($edited);

        $preview = $this->preview->preview($this->definition([
            'percentageValue' => 30.0,
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), $this->currency, editedRuleId: $edited->getId());

        self::assertEqualsWithDelta(100.0, $preview->lines[0]->untaxedBefore, 0.000001);
        self::assertEqualsWithDelta(70.0, $preview->lines[0]->untaxedAfter, 0.000001, 'the new version replaces the old one, they never chain');
    }

    public function testTheSampleIsBounded(): void
    {
        for ($i = 0; $i < 4; ++$i) {
            $this->catalogProduct($this->category);
        }

        $preview = $this->preview->preview($this->definition([
            'criteria' => [CatalogPriceRule::CRITERION_CATEGORY => [$this->category->getId()]],
        ]), $this->currency, sampleSize: 2);

        self::assertSame(4, $preview->affectedProductCount);
        self::assertCount(2, $preview->lines);
    }

    public function testASampleOfOneShowsOneLine(): void
    {
        $this->catalogProduct($this->category);
        $this->catalogProduct($this->category);

        $preview = $this->preview->preview($this->definition([
            'criteria' => [CatalogPriceRule::CRITERION_CATEGORY => [$this->category->getId()]],
        ]), $this->currency, sampleSize: 1);

        self::assertCount(1, $preview->lines);
    }

    public function testASampleSizeBelowOneStillShowsOneLine(): void
    {
        $this->catalogProduct($this->category);
        $this->catalogProduct($this->category);

        $preview = $this->preview->preview($this->definition([
            'criteria' => [CatalogPriceRule::CRITERION_CATEGORY => [$this->category->getId()]],
        ]), $this->currency, sampleSize: 0);

        self::assertSame(2, $preview->affectedProductCount);
        self::assertCount(1, $preview->lines);
    }

    public function testThePreviewPricesWithTheRulesRunningAtTheGivenMoment(): void
    {
        $product = $this->catalogProduct($this->category);
        $scheduled = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0, 'startDate' => new \DateTime('+1 day')]);
        $this->factory->catalogPriceRuleCriterion($scheduled, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $this->getService(RuleRepricer::class)->afterRuleChanged($scheduled);

        $preview = $this->preview->preview($this->definition([
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), $this->currency, now: new \DateTimeImmutable('+2 days'));

        self::assertEqualsWithDelta(90.0, $preview->lines[0]->untaxedBefore, 0.000001, 'the scheduled rule runs at that moment');
        self::assertEqualsWithDelta(72.0, $preview->lines[0]->untaxedAfter, 0.000001);
    }

    public function testACategoryCriterionWithoutSubcategoriesLeavesTheirProductsOut(): void
    {
        $child = $this->factory->category(['parent' => $this->category->getId()]);
        $inParent = $this->catalogProduct($this->category);
        $this->catalogProduct($child);

        $preview = $this->preview->preview($this->definition([
            'includeSubcategories' => false,
            'criteria' => [CatalogPriceRule::CRITERION_CATEGORY => [$this->category->getId()]],
        ]), $this->currency);

        self::assertSame(1, $preview->affectedProductCount);
        self::assertSame($inParent->getId(), $preview->lines[0]->productId);
    }

    public function testAnEditedRuleKeepsItsPlaceAmongRulesOfTheSamePriority(): void
    {
        $product = $this->catalogProduct($this->category);
        $repricer = $this->getService(RuleRepricer::class);
        $edited = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0, 'priority' => 100]);
        $this->factory->catalogPriceRuleCriterion($edited, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $repricer->afterRuleChanged($edited);
        $newer = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0, 'priority' => 100, 'stopProcessing' => true]);
        $this->factory->catalogPriceRuleCriterion($newer, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $repricer->afterRuleChanged($newer);

        $preview = $this->preview->preview($this->definition([
            'percentageValue' => 30.0,
            'priority' => 100,
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), $this->currency, editedRuleId: $edited->getId());

        self::assertEqualsWithDelta(90.0, $preview->lines[0]->untaxedBefore, 0.000001);
        self::assertEqualsWithDelta(63.0, $preview->lines[0]->untaxedAfter, 0.000001, 'the edited rule keeps its place before the newer one, which then stops the chain');
        self::assertTrue($preview->lines[0]->changedByTheRule);
    }

    public function testASaleElementWithoutAPriceIsCountedButNotPriced(): void
    {
        $product = $this->catalogProduct($this->category);
        $this->factory->productSaleElement($product);
        $next = $this->catalogProduct($this->category);

        $preview = $this->preview->preview($this->definition([
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId(), $next->getId()]],
        ]), $this->currency);

        self::assertSame(3, $preview->affectedSaleElementCount);
        self::assertCount(2, $preview->lines);
        self::assertSame($this->defaultSaleElementRef($product), $preview->lines[0]->reference);
        self::assertSame($next->getId(), $preview->lines[1]->productId, 'the sale element without a price is skipped, the sample goes on');
    }

    public function testARunningRuleWithoutAValueInTheCurrencyLeavesThePriceAlone(): void
    {
        $product = $this->catalogProduct($this->category);
        $amount = $this->factory->catalogPriceRule(['active' => true, 'effectType' => CatalogPriceRule::EFFECT_TYPE_AMOUNT]);
        $this->factory->catalogPriceRuleCriterion($amount, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $this->getService(RuleRepricer::class)->afterRuleChanged($amount);

        $preview = $this->preview->preview($this->definition([
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), $this->currency);

        self::assertEqualsWithDelta(100.0, $preview->lines[0]->untaxedBefore, 0.000001);
        self::assertEqualsWithDelta(80.0, $preview->lines[0]->untaxedAfter, 0.000001);
    }

    public function testADefinitionWithoutAValueChangesNothing(): void
    {
        $product = $this->catalogProduct($this->category);

        $preview = $this->preview->preview($this->definition([
            'percentageValue' => null,
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), $this->currency);

        self::assertEqualsWithDelta(100.0, $preview->lines[0]->untaxedBefore, 0.000001);
        self::assertEqualsWithDelta(100.0, $preview->lines[0]->untaxedAfter, 0.000001);
        self::assertFalse($preview->lines[0]->changedByTheRule);
    }

    public function testADefinitionWithoutAValueChangesNothingUnderARunningRule(): void
    {
        $product = $this->catalogProduct($this->category);
        $running = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0]);
        $this->factory->catalogPriceRuleCriterion($running, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $this->getService(RuleRepricer::class)->afterRuleChanged($running);

        $preview = $this->preview->preview($this->definition([
            'percentageValue' => null,
            'criteria' => [CatalogPriceRule::CRITERION_PRODUCT => [$product->getId()]],
        ]), $this->currency);

        self::assertEqualsWithDelta(90.0, $preview->lines[0]->untaxedBefore, 0.000001);
        self::assertEqualsWithDelta(90.0, $preview->lines[0]->untaxedAfter, 0.000001);
        self::assertFalse($preview->lines[0]->changedByTheRule);
    }

    private function definition(array $overrides): CatalogPriceRuleCreateEvent
    {
        return (new CatalogPriceRuleCreateEvent())
            ->setLocale('en_US')
            ->setTitle('Preview')
            ->setActive(true)
            ->setPriority($overrides['priority'] ?? 100)
            ->setStopProcessing($overrides['stopProcessing'] ?? false)
            ->setEffectType(CatalogPriceRule::EFFECT_TYPE_PERCENTAGE)
            ->setPercentageValue(\array_key_exists('percentageValue', $overrides) ? $overrides['percentageValue'] : 20.0)
            ->setIncludeSubcategories($overrides['includeSubcategories'] ?? true)
            ->setCriteria($overrides['criteria'] ?? []);
    }

    private function defaultSaleElementRef(Product $product): string
    {
        return (string) ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne()
            ->getRef();
    }

    private function catalogProduct(Category $category): Product
    {
        return $this->factory->product($category, $this->factory->taxRule(), $this->currency, ['basePrice' => 100.0]);
    }
}
