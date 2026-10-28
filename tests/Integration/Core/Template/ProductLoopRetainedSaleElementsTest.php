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

use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Log\AbstractLogger;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Tests\Support\Trait\ResetsPriceMemos;

/**
 * The complex product loop prices a product by a sale element the criteria of the loop
 * retain, never by one they filter out - on a shop without any customer group, for a
 * customer with a discount or under a public price rule.
 *
 * Each product has a dear sale element in stock (100) and a cheap one out of stock (60);
 * a loop asking for `min_stock` 1 retains the dear one only.
 */
final class ProductLoopRetainedSaleElementsTest extends IntegrationTestCase
{
    use LogsInAsCustomer;
    use ResetsPriceMemos;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::getDefaultCurrency();
        $this->resetPriceMemos();
    }

    protected function tearDown(): void
    {
        $this->resetPriceMemos();

        parent::tearDown();
    }

    public function testACustomerWithADiscountIsChargedAPriceOfARetainedSaleElement(): void
    {
        $product = $this->productWithACheapSaleElementOutOfStock();
        $this->loginAsCustomerInSession($this->customerWithADiscount(10.0));

        $row = $this->complexRow($product, ['min_stock' => 1]);

        // 100 less 10 %: the sale element in stock. 54 would be the one the loop filtered out.
        self::assertEqualsWithDelta(90.0, (float) $row['BEST_PRICE'], 0.000001);
    }

    public function testACustomerWithADiscountStillGetsTheCheapestWhenNoCriterionExcludesIt(): void
    {
        $product = $this->productWithACheapSaleElementOutOfStock();
        $this->loginAsCustomerInSession($this->customerWithADiscount(10.0));

        // The cheap sale element holds no stock, but nothing asks for any: it is retained.
        self::assertEqualsWithDelta(54.0, (float) $this->complexRow($product)['BEST_PRICE'], 0.000001);
    }

    public function testAPublicRulePricesAProductByARetainedSaleElement(): void
    {
        $product = $this->productWithACheapSaleElementOutOfStock();
        $factory = $this->createFixtureFactory();
        $rule = $factory->catalogPriceRule(['active' => true, 'percentageValue' => 20.0]);
        $factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);
        $this->resetPriceMemos();

        $row = $this->complexRow($product, ['min_stock' => 1]);

        // 100 less 20 %, not 60 less 20 %.
        self::assertEqualsWithDelta(80.0, (float) $row['BEST_PRICE'], 0.000001);
        self::assertSame(1, (int) $row['IS_PROMO']);
    }

    public function testAProductWhoseOnlyRetainedSaleElementIsTheCheapOneIsPricedByIt(): void
    {
        $product = $this->productWithACheapSaleElementOutOfStock();
        $this->loginAsCustomerInSession($this->customerWithADiscount(10.0));

        // Retained by the stock criterion on the dear one, by the weight on the cheap one:
        // only the sale elements passing every criterion count.
        [$dear, $cheap] = $this->saleElementsOf($product);
        $dear->setWeight(0.0)->save($this->getPropelConnection());
        $cheap->setQuantity(3)->setWeight(2.0)->save($this->getPropelConnection());

        $row = $this->complexRow($product, ['min_stock' => 1, 'min_weight' => 1.0]);

        self::assertEqualsWithDelta(54.0, (float) $row['BEST_PRICE'], 0.000001);
    }

    public function testEveryProductOfAPageIsPricedByItsRetainedSaleElements(): void
    {
        $first = $this->productWithACheapSaleElementOutOfStock();
        $second = $this->productWithACheapSaleElementOutOfStock();
        $this->loginAsCustomerInSession($this->customerWithADiscount(10.0));

        $prices = [];

        foreach ($this->getService(LoopExecutor::class)->execute('product', ['id' => $first->getId().','.$second->getId(), 'complex' => 1, 'min_stock' => 1]) as $row) {
            $prices[(int) $row->getVarVal()['ID']] = (float) $row->getVarVal()['BEST_PRICE'];
        }

        self::assertEqualsWithDelta(90.0, $prices[$first->getId()], 0.000001);
        self::assertEqualsWithDelta(90.0, $prices[$second->getId()], 0.000001);
    }

    public function testAVisitorOfAShopWithoutAnyPricingKeepsTheSqlPriceOfTheRetainedSaleElements(): void
    {
        $product = $this->productWithACheapSaleElementOutOfStock();

        self::assertEqualsWithDelta(100.0, (float) $this->complexRow($product, ['min_stock' => 1])['BEST_PRICE'], 0.000001);
        self::assertEqualsWithDelta(60.0, (float) $this->complexRow($product)['BEST_PRICE'], 0.000001);
    }

    public function testTheRetainedSaleElementsOfAProductDoNotDependOnTheGroupConcatLengthOfTheServer(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $this->currency, ['basePrice' => 100.0, 'baseQuantity' => 5, 'title' => 'Wide product']);

        // 399 more sale elements, the last created one the cheapest: it comes last in any
        // aggregated list, the first thing a truncated one loses.
        for ($index = 1; $index <= 399; ++$index) {
            $saleElements = $factory->productSaleElement($product, ['quantity' => 5]);
            $price = 399 === $index ? '10.000000' : '100.000000';
            $factory->productPrice($saleElements, $this->currency, ['price' => $price, 'promoPrice' => $price]);
        }

        $this->loginAsCustomerInSession($this->customerWithADiscount(10.0));

        // MySQL cuts a GROUP_CONCAT at 1024 characters by default: about 200 ids of this size: 400 sale elements overflow it whatever their ids.
        $this->getPropelConnection()->exec('SET SESSION group_concat_max_len=1024');

        // The cheapest of the 400 sale elements, less 10 %.
        self::assertEqualsWithDelta(9.0, (float) $this->complexRow($product, ['min_stock' => 1])['BEST_PRICE'], 0.000001);
    }

    public function testAPageReadsItsRetainedSaleElementsInOneQuery(): void
    {
        $ids = [];

        for ($index = 0; $index < 5; ++$index) {
            $ids[] = $this->productWithACheapSaleElementOutOfStock()->getId();
        }

        $this->loginAsCustomerInSession($this->customerWithADiscount(10.0));

        $loopExecutor = $this->getService(LoopExecutor::class);
        $arguments = ['id' => implode(',', $ids), 'complex' => 1, 'min_stock' => 1];

        $statements = [];
        $logger = new class($statements) extends AbstractLogger {
            /** @param list<string> $statements */
            public function __construct(public array &$statements)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->statements[] = (string) $message;
            }
        };

        $connection = $this->getPropelConnection();
        $connection->useDebug(true);
        $connection->setLogger($logger);

        $rows = iterator_to_array($loopExecutor->execute('product', $arguments), false);
        $connection->useDebug(false);

        self::assertCount(5, $rows);
        self::assertCount(1, array_filter(
            $statements,
            static fn (string $sql): bool => str_contains($sql, 'retained_product_id'),
        ));
    }

    private function productWithACheapSaleElementOutOfStock(): Product
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $this->currency, ['basePrice' => 100.0, 'baseQuantity' => 5, 'title' => 'Retained product']);

        $cheap = $factory->productSaleElement($product, ['quantity' => 0]);
        $factory->productPrice($cheap, $this->currency, ['price' => '60.000000', 'promoPrice' => '60.000000']);

        return $product;
    }

    /**
     * @return list<ProductSaleElements> the default one first
     */
    private function saleElementsOf(Product $product): array
    {
        $saleElements = iterator_to_array(
            ProductSaleElementsQuery::create()->filterByProductId($product->getId())->orderByIsDefault(Criteria::DESC)->orderById()->find(),
            false,
        );

        return array_values($saleElements);
    }

    private function customerWithADiscount(float $discount): Customer
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $customer->setDiscount((string) $discount)->save($this->getPropelConnection());

        return $customer;
    }

    private function complexRow(Product $product, array $arguments = []): array
    {
        foreach ($this->getService(LoopExecutor::class)->execute('product', $arguments + ['id' => $product->getId(), 'complex' => 1]) as $row) {
            return $row->getVarVal();
        }

        self::fail('The loop returned no product.');
    }
}
