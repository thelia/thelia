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

namespace Thelia\Tests\Integration\Domain\Pricing\Rule;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Domain\Pricing\Rule\Scope\ScopeMaterializer;
use Thelia\Domain\Pricing\Rule\Storage\PublicPriceSegmentWriter;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Model\Category;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A change small enough is repriced in the request that made it; a larger one is left
 * to the recompute command, with the rule marked as owing it, and the log says so.
 */
final class RuleRepricerTest extends ActionIntegrationTestCase
{
    private TestHandler $log;

    private RuleRepricer $repricer;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->log = new TestHandler();
        $this->repricer = new RuleRepricer(
            $this->getService(ScopeMaterializer::class),
            $this->getService(PublicPriceSegmentWriter::class),
            new Logger('test', [$this->log]),
        );
        $this->category = $this->factory->category();
    }

    protected function tearDown(): void
    {
        ConfigQuery::write(RuleRepricer::INLINE_LIMIT_CONFIG, (string) RuleRepricer::DEFAULT_INLINE_LIMIT);

        parent::tearDown();
    }

    public function testARuleCarryingACriterionNoModuleResolvesIsReported(): void
    {
        $rule = $this->factory->catalogPriceRule();
        $this->factory->catalogPriceRuleCriterion($rule, 'some_module_type', 1);

        $this->repricer->afterRuleChanged($rule);

        self::assertSame([['rule_id' => $rule->getId(), 'types' => ['some_module_type']]], $this->logged(Level::Warning));
    }

    public function testARuleWhoseCriteriaAreAllResolvedIsRepricedWithoutAWarning(): void
    {
        $rule = $this->ruleNaming($this->product());

        $this->repricer->afterRuleChanged($rule);

        self::assertSame([], $this->logged(Level::Warning));
        self::assertFalse($this->isDirty($rule));
        self::assertNotNull($rule->getComputedAt());
    }

    public function testAChangeOfExactlyTheInlineLimitIsRepricedAtOnce(): void
    {
        ConfigQuery::write(RuleRepricer::INLINE_LIMIT_CONFIG, '1');
        $rule = $this->ruleNaming($this->product());

        $this->repricer->afterRuleChanged($rule);

        self::assertFalse($this->isDirty($rule));
        self::assertSame([], $this->logged(Level::Info));
    }

    public function testANegativeInlineLimitStillRepricesAChangeTouchingNothing(): void
    {
        ConfigQuery::write(RuleRepricer::INLINE_LIMIT_CONFIG, '-5');
        $rule = $this->factory->catalogPriceRule();
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, 999_999_999);

        $this->repricer->afterRuleChanged($rule);

        self::assertFalse($this->isDirty($rule));
    }

    public function testAChangeOverTheInlineLimitIsLeftToTheCommand(): void
    {
        ConfigQuery::write(RuleRepricer::INLINE_LIMIT_CONFIG, '0');
        $rule = $this->ruleNaming($this->product());

        $this->repricer->afterRuleChanged($rule);

        self::assertTrue($this->isDirty($rule));
        self::assertSame([['rule_id' => $rule->getId(), 'count' => 1]], $this->logged(Level::Info));
    }

    public function testADeletionOverTheInlineLimitLeavesEveryTurnedOnRuleToTheCommand(): void
    {
        ConfigQuery::write(RuleRepricer::INLINE_LIMIT_CONFIG, '0');
        $product = $this->product();
        $running = $this->factory->catalogPriceRule(['active' => true]);
        $turnedOff = $this->factory->catalogPriceRule(['active' => false]);

        $this->repricer->afterRuleDeleted([$this->pseIdOf($product)]);

        self::assertTrue($this->isDirty($running));
        self::assertFalse($this->isDirty($turnedOff));
        self::assertSame([['rule_id' => null, 'count' => 1]], $this->logged(Level::Info));
    }

    public function testOnlyTheRulesNamingTheChangedTargetAreRepriced(): void
    {
        $brand = $this->factory->brand();
        $otherBrand = $this->factory->brand();
        $namingIt = $this->ruleWithCriterion(CatalogPriceRule::CRITERION_BRAND, $brand->getId());
        $namingAnother = $this->ruleWithCriterion(CatalogPriceRule::CRITERION_BRAND, $otherBrand->getId());

        $this->repricer->afterCriterionTargetChanged(CatalogPriceRule::CRITERION_BRAND, $brand->getId());

        self::assertFalse($this->isDirty($namingIt));
        self::assertTrue($this->isDirty($namingAnother));
    }

    public function testAMovedCategoryRepricesEveryRuleNamingAnyCategory(): void
    {
        $namingAnotherCategory = $this->ruleWithCriterion(CatalogPriceRule::CRITERION_CATEGORY, $this->factory->category()->getId());
        $namingABrand = $this->ruleWithCriterion(CatalogPriceRule::CRITERION_BRAND, $this->factory->brand()->getId());

        $this->repricer->afterCriterionTargetChanged(CatalogPriceRule::CRITERION_CATEGORY, null);

        self::assertFalse($this->isDirty($namingAnotherCategory));
        self::assertTrue($this->isDirty($namingABrand));
    }

    public function testAFullRunClearsTheMarkOfEveryRule(): void
    {
        $rule = $this->factory->catalogPriceRule(['active' => true]);
        $rule->setDirty(true)->save();

        $this->repricer->markAllComputed();

        self::assertFalse($this->isDirty($rule));
        self::assertNotNull($rule->getComputedAt());
    }

    public function testTheCommandCountsTheRulesItBroughtUpToDate(): void
    {
        $alreadyDirty = CatalogPriceRuleQuery::create()->filterByDirty(true)->count();
        $first = $this->factory->catalogPriceRule();
        $first->setDirty(true)->save();
        $second = $this->factory->catalogPriceRule();
        $second->setDirty(true)->save();

        self::assertSame($alreadyDirty + 2, $this->repricer->repriceDirtyRules());
        self::assertFalse($this->isDirty($first));
        self::assertFalse($this->isDirty($second));
    }

    public function testAProductChangeReportsWhatItDidToEveryRule(): void
    {
        $product = $this->product();
        $namingIt = $this->ruleNaming($product);
        $this->factory->catalogPriceRule();

        $changes = $this->repricer->afterProductChanged($product->getId());

        self::assertCount(CatalogPriceRuleQuery::create()->count(), $changes);
        $afterByRule = [];
        foreach ($changes as $change) {
            $afterByRule[$change->ruleId] = $change->after;
        }
        self::assertSame([$this->pseIdOf($product)], $afterByRule[$namingIt->getId()] ?? null);
    }

    private function product(): Product
    {
        return $this->factory->product($this->category, $this->factory->taxRule(), $this->factory->currency(), ['basePrice' => 100.0]);
    }

    private function ruleNaming(Product $product): CatalogPriceRule
    {
        $rule = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0]);
        $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());

        return $rule;
    }

    /**
     * A rule owing a recompute, naming one target of one criterion type.
     */
    private function ruleWithCriterion(string $type, int $targetId): CatalogPriceRule
    {
        $rule = $this->factory->catalogPriceRule();
        $this->factory->catalogPriceRuleCriterion($rule, $type, $targetId);
        $rule->setDirty(true)->save();

        return $rule;
    }

    private function isDirty(CatalogPriceRule $rule): bool
    {
        $rule->reload();

        return (bool) $rule->getDirty();
    }

    private function pseIdOf(Product $product): int
    {
        return (int) ProductSaleElementsQuery::create()->filterByProductId($product->getId())->select('Id')->findOne();
    }

    /**
     * @return list<array<string, mixed>> the context of every record logged at that level
     */
    private function logged(Level $level): array
    {
        return array_values(array_map(
            static fn (LogRecord $record): array => $record->context,
            array_filter($this->log->getRecords(), static fn (LogRecord $record): bool => $level === $record->level),
        ));
    }
}
