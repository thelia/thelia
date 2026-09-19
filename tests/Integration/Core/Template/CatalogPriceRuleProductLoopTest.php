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
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;

/**
 * The product loops show the price a rule gives, for everyone, in place of the
 * promo price - and order by it.
 */
final class CatalogPriceRuleProductLoopTest extends IntegrationTestCase
{
    use LogsInAsCustomer;

    private Currency $currency;

    private Category $category;

    private TaxRule $taxRule;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->currency = $factory->currency();
        $this->category = $factory->category();
        $this->taxRule = $factory->taxRule();
    }

    public function testAVisitorReadsTheRulePriceOnTheProductLoop(): void
    {
        $product = $this->catalogProduct();
        $this->ruleOn($product, 20.0);

        $row = $this->productRow($product);

        self::assertSame(1, (int) $row['IS_PROMO']);
        self::assertEqualsWithDelta(80.0, (float) $row['PROMO_PRICE'], 0.000001);
        self::assertEqualsWithDelta(100.0, (float) $row['PRICE'], 0.000001, 'The catalog price is untouched.');
        self::assertSame(1, (int) $row['SHOW_ORIGINAL_PRICE']);
    }

    public function testTheRuleDecidesWhetherTheCatalogPriceIsStruckThrough(): void
    {
        $product = $this->catalogProduct();
        $this->ruleOn($product, 20.0, ['displayInitialPrice' => false]);

        self::assertSame(0, (int) $this->productRow($product)['SHOW_ORIGINAL_PRICE']);
    }

    /**
     * Frozen decision: a rule replaces the promo price whatever it was, a manual
     * one included; the manual price is not lost and comes back when the rule ends.
     */
    public function testTheRulePriceReplacesAManualPromoPrice(): void
    {
        $product = $this->catalogProduct();
        $pse = $this->defaultPseFor($product);
        $pse->setPromo(1)->save();
        ProductPriceQuery::create()->filterByProductSaleElementsId($pse->getId())->findOne()->setPromoPrice('50.000000')->save();
        $rule = $this->ruleOn($product, 20.0);

        self::assertEqualsWithDelta(80.0, (float) $this->productRow($product)['PROMO_PRICE'], 0.000001);

        $rule->setActive(false)->save();
        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);
        // Two reads in one request here, where the shop has one per request: the
        // per-request memos are reset by hand the way kernel.reset would.
        $this->getService(EffectivePriceCatalog::class)->reset();
        $this->getService(PricingActivityChecker::class)->reset();

        self::assertEqualsWithDelta(50.0, (float) $this->productRow($product)['PROMO_PRICE'], 0.000001, 'the manual promo price is back');
    }

    public function testTheBackOfficeReadsTheCatalog(): void
    {
        $product = $this->catalogProduct();
        $this->ruleOn($product, 20.0);

        $row = $this->productRow($product, ['backend_context' => 1]);

        self::assertSame(0, (int) $row['IS_PROMO']);
    }

    public function testTheComplexLoopAndTheSaleElementLoopReadTheRulePriceToo(): void
    {
        $product = $this->catalogProduct();
        $this->ruleOn($product, 20.0);

        $complex = $this->productRow($product, ['complex' => 1]);
        self::assertSame(1, (int) $complex['IS_PROMO']);
        self::assertEqualsWithDelta(80.0, (float) $complex['BEST_PRICE'], 0.000001);

        $saleElement = $this->firstRow('product_sale_elements', ['id' => $this->defaultPseFor($product)->getId()]);
        self::assertSame(1, (int) $saleElement['IS_PROMO']);
        self::assertEqualsWithDelta(80.0, (float) $saleElement['PROMO_PRICE'], 0.000001);
    }

    /**
     * The stored price is what a listing can be ordered by: a shop at half price
     * comes before a cheaper product at its catalog price.
     */
    public function testThePriceOrderHonoursTheRulePrice(): void
    {
        $halved = $this->catalogProduct(100.0);
        $cheap = $this->catalogProduct(60.0);
        $this->ruleOn($halved, 50.0);

        $ids = $this->productIds(['id' => $halved->getId().','.$cheap->getId(), 'order' => 'min_price']);
        self::assertSame([$halved->getId(), $cheap->getId()], $ids);

        $ids = $this->productIds(['id' => $halved->getId().','.$cheap->getId(), 'order' => 'max_price']);
        self::assertSame([$cheap->getId(), $halved->getId()], $ids);
    }

    public function testANamedCustomerReadsTheirReservedRulePriceAndNobodyElseDoes(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->newCustomer();
        $rule = $this->ruleOn($product, 30.0, ['audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS], $customer);

        self::assertSame(0, (int) $this->productRow($product)['IS_PROMO'], 'a visitor sees nothing of it');

        $this->loginAsCustomerInSession($this->newCustomer());
        self::assertSame(0, (int) $this->productRow($product)['IS_PROMO'], 'a customer not named sees nothing of it');

        $this->loginAsCustomerInSession($customer);
        $row = $this->productRow($product);
        self::assertSame(1, (int) $row['IS_PROMO']);
        self::assertEqualsWithDelta(70.0, (float) $row['PROMO_PRICE'], 0.000001);
        self::assertSame($rule->getId(), $rule->getId());
    }

    private function ruleOn(Product $product, float $percentage, array $overrides = [], ?Customer $customer = null): CatalogPriceRule
    {
        $factory = $this->createFixtureFactory();
        $rule = $factory->catalogPriceRule($overrides + ['active' => true, 'percentageValue' => $percentage]);
        $factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());

        if (null !== $customer) {
            $factory->catalogPriceRuleCustomer($rule, $customer);
        }

        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        return $rule;
    }

    private function catalogProduct(float $basePrice = 100.0): Product
    {
        return $this->createFixtureFactory()->product(
            $this->category,
            $this->taxRule,
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => $basePrice, 'title' => 'Rule loop product'],
        );
    }

    private function newCustomer(): Customer
    {
        $factory = $this->createFixtureFactory();

        return $factory->customer($factory->customerTitle());
    }

    private function defaultPseFor(Product $product): ProductSaleElements
    {
        $pse = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(true)->findOne();
        self::assertNotNull($pse);

        return $pse;
    }

    private function productRow(Product $product, array $arguments = []): array
    {
        $row = $this->firstRow('product', $arguments + ['id' => $product->getId()]);
        self::assertNotNull($row);

        return $row;
    }

    private function firstRow(string $loop, array $arguments): ?array
    {
        foreach ($this->getService(LoopExecutor::class)->execute($loop, $arguments) as $row) {
            return $row->getVarVal();
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function productIds(array $arguments): array
    {
        $ids = [];

        foreach ($this->getService(LoopExecutor::class)->execute('product', $arguments) as $row) {
            $ids[] = (int) $row->getVarVal()['ID'];
        }

        return $ids;
    }
}
