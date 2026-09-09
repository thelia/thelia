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
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Sale;
use Thelia\Model\TaxRule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;

/**
 * What the legacy loops of a Smarty template make of a reserved operation.
 *
 * They are the other half of the front — the back office runs on them too — so
 * the two rules the API enforces have to hold here as well: the products of a
 * hidden operation are out of the catalog of whoever it is not open to, and the
 * customer it is open to reads the price it gives them.
 *
 * The back office is deliberately exempt from both: an administrator setting an
 * operation up has to see what is in it.
 */
final class ReservedSaleProductLoopTest extends IntegrationTestCase
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

    public function testAVisitorDoesNotSeeTheProductsOfAHiddenReservedOperation(): void
    {
        $product = $this->catalogProduct();
        $this->hiddenReservedSaleOn($product, $this->newCustomer());

        self::assertNull($this->productRow($product));
    }

    public function testACustomerTheOperationDoesNotNameDoesNotSeeThemEither(): void
    {
        $product = $this->catalogProduct();
        $this->hiddenReservedSaleOn($product, $this->newCustomer());
        $this->loginAsCustomerInSession($this->newCustomer());

        self::assertNull($this->productRow($product));
    }

    public function testTheCustomerTheOperationNamesSeesThem(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->newCustomer();
        $this->hiddenReservedSaleOn($product, $customer);
        $this->loginAsCustomerInSession($customer);

        self::assertNotNull($this->productRow($product));
    }

    public function testTheBackOfficeSeesEverything(): void
    {
        $product = $this->catalogProduct();
        $this->hiddenReservedSaleOn($product, $this->newCustomer());

        self::assertNotNull($this->productRow($product, ['backend_context' => 1]));
    }

    public function testTheHiddenProductsAreOutOfTheComplexLoopToo(): void
    {
        $product = $this->catalogProduct();
        $this->hiddenReservedSaleOn($product, $this->newCustomer());

        self::assertNull($this->productRow($product, ['complex' => 1]));
    }

    public function testAPublicOperationHidesNothing(): void
    {
        $product = $this->catalogProduct();
        $factory = $this->createFixtureFactory();
        $sale = $factory->sale([
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_PUBLIC,
            'hideProducts' => true,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);
        $factory->saleProduct($sale, $product);

        self::assertNotNull($this->productRow($product));
    }

    public function testTheNamedCustomerReadsTheReservedPriceOnTheProductLoop(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->newCustomer();
        $this->reservedSaleOn($product, $customer, offset: 10.0);
        $this->loginAsCustomerInSession($customer);

        $row = $this->productRow($product);

        self::assertSame(1, (int) $row['IS_PROMO'], 'The reserved price is a promo for the customer it is resolved for.');
        self::assertEqualsWithDelta(90.0, (float) $row['PROMO_PRICE'], 0.000001);
        self::assertEqualsWithDelta(90.0, (float) $row['BEST_PRICE'], 0.000001);
        // The offset is taken off the TAXED price and converted back, so a 10%
        // operation on a 100 HT / 120 TTC product charges 108 TTC, that is 90 HT.
        self::assertEqualsWithDelta(108.0, (float) $row['TAXED_PROMO_PRICE'], 0.000001);
        self::assertEqualsWithDelta(108.0, (float) $row['BEST_TAXED_PRICE'], 0.000001);
        self::assertEqualsWithDelta(100.0, (float) $row['PRICE'], 0.000001, 'The catalog price is untouched.');
    }

    public function testAVisitorReadsTheCatalogPriceOnTheProductLoop(): void
    {
        $product = $this->catalogProduct();
        $this->reservedSaleOn($product, $this->newCustomer(), offset: 10.0);

        $row = $this->productRow($product);

        self::assertSame(0, (int) $row['IS_PROMO']);
        self::assertEqualsWithDelta(100.0, (float) $row['BEST_PRICE'], 0.000001);
    }

    public function testACustomerTheOperationDoesNotNameReadsTheCatalogPrice(): void
    {
        $product = $this->catalogProduct();
        $this->reservedSaleOn($product, $this->newCustomer(), offset: 10.0);
        $this->loginAsCustomerInSession($this->newCustomer());

        $row = $this->productRow($product);

        self::assertSame(0, (int) $row['IS_PROMO']);
        self::assertEqualsWithDelta(100.0, (float) $row['BEST_PRICE'], 0.000001);
    }

    public function testTheBackOfficeReadsTheCatalogPriceWhoeverIsSignedIn(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->newCustomer();
        $this->reservedSaleOn($product, $customer, offset: 10.0);
        $this->loginAsCustomerInSession($customer);

        $row = $this->productRow($product, ['backend_context' => 1]);

        self::assertSame(0, (int) $row['IS_PROMO'], 'The catalog is what the back office edits.');
        self::assertEqualsWithDelta(100.0, (float) $row['BEST_PRICE'], 0.000001);
    }

    public function testTheNamedCustomerReadsTheReservedPriceOnTheSaleElementLoop(): void
    {
        $product = $this->catalogProduct();
        $customer = $this->newCustomer();
        $this->reservedSaleOn($product, $customer, offset: 10.0);
        $this->loginAsCustomerInSession($customer);

        $row = $this->saleElementRow($this->defaultPseFor($product));

        self::assertSame(1, (int) $row['IS_PROMO']);
        self::assertEqualsWithDelta(90.0, (float) $row['PROMO_PRICE'], 0.000001);
        self::assertEqualsWithDelta(100.0, (float) $row['PRICE'], 0.000001);
    }

    public function testAVisitorReadsTheCatalogPriceOnTheSaleElementLoop(): void
    {
        $product = $this->catalogProduct();
        $this->reservedSaleOn($product, $this->newCustomer(), offset: 10.0);

        $row = $this->saleElementRow($this->defaultPseFor($product));

        self::assertSame(0, (int) $row['IS_PROMO']);
        self::assertEqualsWithDelta(100.0, (float) $row['PROMO_PRICE'], 0.000001);
    }

    /**
     * DISPLAY_INITIAL_PRICE is read from whichever active operation covers the
     * product. A reserved one the visitor is not part of must not be that one:
     * it would decide how a price they are not getting is displayed.
     */
    public function testAReservedOperationDoesNotDecideHowAVisitorSeesThePrice(): void
    {
        $product = $this->catalogProduct();
        $factory = $this->createFixtureFactory();
        $sale = $factory->sale([
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'displayInitialPrice' => false,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);
        $factory->saleProduct($sale, $product);
        $factory->saleCustomer($sale, $this->newCustomer());
        $factory->saleOffsetCurrency($sale, $this->currency, 10.0);

        $row = $this->productRow($product);

        self::assertSame(
            1,
            (int) $row['SHOW_ORIGINAL_PRICE'],
            'An operation the visitor is not part of has nothing to say about their price.',
        );
    }

    private function catalogProduct(): Product
    {
        return $this->createFixtureFactory()->product(
            $this->category,
            $this->taxRule,
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => 100.0, 'title' => 'Loop product'],
        );
    }

    private function hiddenReservedSaleOn(Product $product, Customer $customer): Sale
    {
        return $this->reservedSaleOn($product, $customer, offset: 10.0, hideProducts: true);
    }

    private function reservedSaleOn(
        Product $product,
        Customer $customer,
        float $offset,
        bool $hideProducts = false,
    ): Sale {
        $factory = $this->createFixtureFactory();
        $sale = $factory->sale([
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'hideProducts' => $hideProducts,
            'priceOffsetType' => Sale::OFFSET_TYPE_PERCENTAGE,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);
        $factory->saleProduct($sale, $product);
        $factory->saleCustomer($sale, $customer);
        $factory->saleOffsetCurrency($sale, $this->currency, $offset);

        return $sale;
    }

    private function newCustomer(): Customer
    {
        $factory = $this->createFixtureFactory();

        return $factory->customer($factory->customerTitle());
    }

    private function defaultPseFor(Product $product): ProductSaleElements
    {
        return ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function productRow(Product $product, array $arguments = []): ?array
    {
        return $this->firstRow('product', $arguments + ['id' => $product->getId()]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function saleElementRow(ProductSaleElements $saleElements, array $arguments = []): ?array
    {
        return $this->firstRow('product_sale_elements', $arguments + ['id' => $saleElements->getId()]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firstRow(string $loop, array $arguments): ?array
    {
        $result = $this->getService(LoopExecutor::class)->execute($loop, $arguments);

        foreach ($result as $row) {
            return $row->getVarVal();
        }

        return null;
    }
}
