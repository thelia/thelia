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

use Thelia\Domain\Pricing\Rule\Scope\ScopeMaterializer;
use Thelia\Domain\Pricing\Rule\Storage\ScopeReader;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * Which rules cover which sale elements, as the materialized scope says.
 */
final class ScopeReaderTest extends ActionIntegrationTestCase
{
    private ScopeReader $reader;

    private ScopeMaterializer $materializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = $this->getService(ScopeReader::class);
        $this->materializer = $this->getService(ScopeMaterializer::class);
    }

    public function testOnlyTheAskedRulesCoveringTheAskedSaleElementsAreAnswered(): void
    {
        $asked = $this->defaultPseFor($this->catalogProduct());
        $other = $this->defaultPseFor($this->catalogProduct());
        $askedRule = $this->ruleOn([$asked, $other]);
        $otherRule = $this->ruleOn([$asked]);

        self::assertSame([$asked->getId() => [$askedRule->getId()]], $this->reader->coveringRules([$asked->getId()], [$askedRule->getId()]));
        self::assertSame([], $this->reader->coveringRules([$asked->getId()], []));
        self::assertSame([], $this->reader->coveringRules([], [$askedRule->getId(), $otherRule->getId()]));
    }

    public function testAnIdThatIsNotAnIntegerNeverWidensTheStatement(): void
    {
        $asked = $this->defaultPseFor($this->catalogProduct());
        $other = $this->defaultPseFor($this->catalogProduct());
        $askedRule = $this->ruleOn([$asked, $other]);
        $this->ruleOn([$asked, $other]);
        $expected = [$asked->getId() => [$askedRule->getId()]];

        self::assertSame($expected, $this->reader->coveringRules([$asked->getId().') OR (1 = 1'], [$askedRule->getId()]));
        self::assertSame($expected, $this->reader->coveringRules([$asked->getId()], [$askedRule->getId().') OR (1 = 1']));
    }

    public function testEverySaleElementARuleCoversIsListed(): void
    {
        $first = $this->defaultPseFor($this->catalogProduct());
        $second = $this->defaultPseFor($this->catalogProduct());
        $uncovered = $this->defaultPseFor($this->catalogProduct());
        $rule = $this->ruleOn([$first, $second]);

        self::assertEqualsCanonicalizing([$first->getId(), $second->getId()], $this->reader->productSaleElementsOf($rule->getId()));

        $covered = $this->reader->allCoveredProductSaleElements();
        self::assertContains($first->getId(), $covered);
        self::assertContains($second->getId(), $covered);
        self::assertNotContains($uncovered->getId(), $covered);
    }

    /**
     * @param list<ProductSaleElements> $productSaleElements
     */
    private function ruleOn(array $productSaleElements): CatalogPriceRule
    {
        $rule = $this->factory->catalogPriceRule(['percentageValue' => 10.0, 'active' => true]);

        foreach ($productSaleElements as $pse) {
            $this->factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $pse->getProductId());
        }

        $this->materializer->materializeRule($rule);

        return $rule;
    }

    private function catalogProduct(): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            Currency::getDefaultCurrency(),
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
