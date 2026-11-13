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
use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Integration\Domain\Pricing\PriceVisibility\PriceDisplayFixtures;
use Thelia\Tests\Support\Trait\ResetsPriceMemos;

/**
 * The product loops of the historical theme print what the visitor is shown: a price the shop or
 * the product hides from this visitor (identified customers only, or on request) comes out empty,
 * in every price field of the product loop, of its complex form and of the sale element loop,
 * and costs no statement per row. The back office reads the catalog whatever the mode.
 */
final class ProductLoopPriceDisplayTest extends IntegrationTestCase
{
    use ForgetsPooledModels;
    use LogsInAsCustomer;
    use PriceDisplayFixtures;
    use RecordsSqlQueries;
    use ResetsPriceMemos;

    private const PRODUCT_FIELDS = ['PRICE', 'PRICE_TAX', 'TAXED_PRICE', 'PROMO_PRICE', 'PROMO_PRICE_TAX', 'TAXED_PROMO_PRICE', 'BEST_PRICE', 'BEST_PRICE_TAX', 'BEST_TAXED_PRICE'];

    private const SALE_ELEMENT_FIELDS = ['PRICE', 'PRICE_TAX', 'TAXED_PRICE', 'PROMO_PRICE', 'PROMO_PRICE_TAX', 'TAXED_PROMO_PRICE'];

    private const COMPLEX_FIELDS = ['BEST_PRICE', 'BEST_PRICE_TAX', 'BEST_TAXED_PRICE'];

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->forgetEverything();
    }

    protected function tearDown(): void
    {
        $this->logoutCustomer();
        $this->forgetEverything();

        parent::tearDown();
    }

    public function testAShopThatShowsEveryPriceKeepsEveryField(): void
    {
        $product = $this->product(40.0);

        $this->assertShown($this->row('product', ['id' => $product->getId()]), self::PRODUCT_FIELDS, 40.0);
        $this->assertShown($this->row('product', ['id' => $product->getId(), 'complex' => 1]), self::COMPLEX_FIELDS, 40.0);
        $this->assertShown($this->row('product_sale_elements', ['id' => $product->getDefaultSaleElements()->getId()]), self::SALE_ELEMENT_FIELDS, 40.0);
    }

    public function testAVisitorIsShownNoPriceWhenTheShopShowsThemToIdentifiedCustomersOnly(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $product = $this->product(40.0);

        $this->assertHidden($this->row('product', ['id' => $product->getId()]), self::PRODUCT_FIELDS);
        $this->assertHidden($this->row('product', ['id' => $product->getId(), 'complex' => 1]), self::COMPLEX_FIELDS);
        $this->assertHidden($this->row('product_sale_elements', ['id' => $product->getDefaultSaleElements()->getId()]), self::SALE_ELEMENT_FIELDS);
    }

    public function testAnIdentifiedCustomerIsShownThePrices(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $product = $this->product(40.0);
        $this->loginAsCustomerInSession($this->customer());

        $this->assertShown($this->row('product', ['id' => $product->getId()]), self::PRODUCT_FIELDS, 40.0);
        $this->assertShown($this->row('product', ['id' => $product->getId(), 'complex' => 1]), self::COMPLEX_FIELDS, 40.0);
        $this->assertShown($this->row('product_sale_elements', ['id' => $product->getDefaultSaleElements()->getId()]), self::SALE_ELEMENT_FIELDS, 40.0);
    }

    public function testAPriceOnRequestIsShownToNobody(): void
    {
        $this->shopShows(PriceDisplayMode::OnRequest);
        $product = $this->product(40.0);
        $this->loginAsCustomerInSession($this->customer());

        $this->assertHidden($this->row('product', ['id' => $product->getId()]), self::PRODUCT_FIELDS);
        $this->assertHidden($this->row('product', ['id' => $product->getId(), 'complex' => 1]), self::COMPLEX_FIELDS);
        $this->assertHidden($this->row('product_sale_elements', ['id' => $product->getDefaultSaleElements()->getId()]), self::SALE_ELEMENT_FIELDS);
    }

    public function testAProductThatStatesItsOwnModeIsHiddenAloneInAPage(): void
    {
        $hidden = $this->product(40.0);
        $shown = $this->product(25.0);
        $this->productShows($hidden, PriceDisplayMode::OnRequest);

        $rows = $this->rows('product', ['id' => $hidden->getId().','.$shown->getId()]);

        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            if ((int) $row['ID'] === $hidden->getId()) {
                $this->assertHidden($row, self::PRODUCT_FIELDS);
            } else {
                $this->assertShown($row, self::PRODUCT_FIELDS, 25.0);
            }
        }

        $saleElements = $this->rows('product_sale_elements', ['product' => $hidden->getId()]);
        self::assertNotSame([], $saleElements);
        $this->assertHidden($saleElements[0], self::SALE_ELEMENT_FIELDS);
        $this->assertShown($this->rows('product_sale_elements', ['product' => $shown->getId()])[0], self::SALE_ELEMENT_FIELDS, 25.0);
    }

    public function testARangeOnThePriceListsOnlyTheProductsWhosePriceTheVisitorIsShown(): void
    {
        $hidden = $this->product(40.0);
        $shown = $this->product(25.0);
        $this->productShows($hidden, PriceDisplayMode::OnRequest);
        $both = $hidden->getId().','.$shown->getId();

        foreach ([['min_price' => 10], ['max_price' => 50], ['min_price' => 10, 'complex' => 1], ['max_price' => 50, 'complex' => 1]] as $range) {
            self::assertSame([$shown->getId()], $this->ids(['id' => $both] + $range), 'a range on the price tells the price it hides: '.json_encode($range));
        }

        self::assertEqualsCanonicalizing([$hidden->getId(), $shown->getId()], $this->ids(['id' => $both]), 'a page that names no price keeps every product');
        self::assertEqualsCanonicalizing([$hidden->getId(), $shown->getId()], $this->ids(['id' => $both, 'min_price' => 10, 'backend_context' => 1]), 'the back office filters on the catalog');
    }

    public function testARangeOnThePriceListsTheProductsAnIdentifiedCustomerIsShown(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $first = $this->product(40.0);
        $second = $this->product(25.0);
        $both = $first->getId().','.$second->getId();

        self::assertSame([], $this->ids(['id' => $both, 'min_price' => 10]));

        $this->loginAsCustomerInSession($this->customer());
        $this->forgetEverything();

        self::assertEqualsCanonicalizing([$first->getId(), $second->getId()], $this->ids(['id' => $both, 'min_price' => 10]));
    }

    public function testTheBackOfficeReadsTheCatalogWhateverTheMode(): void
    {
        $this->shopShows(PriceDisplayMode::OnRequest);
        $product = $this->product(40.0);

        $this->assertShown($this->row('product', ['id' => $product->getId(), 'backend_context' => 1]), self::PRODUCT_FIELDS, 40.0);
        $this->assertShown($this->row('product_sale_elements', ['id' => $product->getDefaultSaleElements()->getId(), 'backend_context' => 1]), self::SALE_ELEMENT_FIELDS, 40.0);
    }

    public function testHidingThePricesCostsTheSameFewStatementsWhateverTheNumberOfRows(): void
    {
        $products = [];

        for ($i = 0; $i < 10; ++$i) {
            $products[] = $this->product(10.0 + $i);
        }

        $productIds = array_map(static fn (Product $product): int => (int) $product->getId(), $products);
        $saleElementIds = array_map(static fn (Product $product): int => (int) $product->getDefaultSaleElements()->getId(), $products);

        foreach (['product' => $productIds, 'product_sale_elements' => $saleElementIds] as $loop => $ids) {
            $few = ['id' => implode(',', \array_slice($ids, 0, 3))];
            $many = ['id' => implode(',', $ids)];

            // The first run loads what the process keeps (languages, tax rules): not a cost per row.
            $this->shopShows(PriceDisplayMode::Visible);
            $this->countStatements($loop, $many);

            $visibleFew = $this->countStatements($loop, $few);
            $visibleMany = $this->countStatements($loop, $many);

            $this->shopShows(PriceDisplayMode::LoggedIn);
            $hiddenFew = $this->countStatements($loop, $few);
            $hiddenMany = $this->countStatements($loop, $many);

            self::assertSame($hiddenFew - $visibleFew, $hiddenMany - $visibleMany, 'Hiding the prices of more rows ran more statements in the loop '.$loop);
            self::assertLessThanOrEqual(3, $hiddenMany - $visibleMany, 'Hiding the prices of a page costs a handful of statements at most: '.$loop);
        }
    }

    private function countStatements(string $loop, array $arguments): int
    {
        $this->forgetEverything();

        return \count($this->recordSqlQueriesWithoutPooledModels(fn () => $this->rows($loop, $arguments)));
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string>         $fields
     */
    private function assertHidden(array $row, array $fields): void
    {
        foreach ($fields as $field) {
            self::assertSame('', $row[$field], $field.' is empty when the price is hidden');
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string>         $fields
     */
    private function assertShown(array $row, array $fields, float $price): void
    {
        foreach ($fields as $field) {
            self::assertNotSame('', $row[$field], $field.' is filled when the price is shown');
            self::assertNotNull($row[$field], $field);
        }

        self::assertEqualsWithDelta($price, (float) $row[$fields[0]], 0.000001);
    }

    private function product(float $price): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            Currency::getDefaultCurrency(),
            ['basePrice' => $price, 'baseQuantity' => 100, 'title' => 'Price display product'],
        );
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    private function forgetEverything(): void
    {
        $this->forgetPriceDisplayMemos();
        $this->resetPriceMemos();
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function row(string $loop, array $arguments): array
    {
        $rows = $this->rows($loop, $arguments);
        self::assertNotSame([], $rows);

        return $rows[0];
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<int>
     */
    private function ids(array $arguments): array
    {
        return array_map(static fn (array $row): int => (int) $row['ID'], $this->rows('product', $arguments));
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $loop, array $arguments): array
    {
        $rows = [];

        foreach ($this->getService(LoopExecutor::class)->execute($loop, $arguments) as $row) {
            $rows[] = $row->getVarVal();
        }

        return $rows;
    }
}
