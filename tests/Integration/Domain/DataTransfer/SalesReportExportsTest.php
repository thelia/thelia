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

namespace Thelia\Tests\Integration\Domain\DataTransfer;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Domain\DataTransfer\Exception\DataTransferNoDataFoundException;
use Thelia\Domain\DataTransfer\Export\AbstractExport;
use Thelia\Domain\DataTransfer\Export\Type\SalesByCategoryExport;
use Thelia\Domain\DataTransfer\Export\Type\SalesByCustomerExport;
use Thelia\Domain\DataTransfer\Export\Type\SalesByProductExport;
use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Domain\Report\Sales\CustomerSort;
use Thelia\Domain\Report\Sales\SalesReportCalculator;
use Thelia\Domain\Report\Sales\SalesReportCriteria;
use Thelia\Domain\Report\Sales\StatusScope;
use Thelia\Model\Category;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Export;
use Thelia\Model\ExportQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Product;
use Thelia\Model\TaxRule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The sales exports write the rows the sales report computes, for the period of the job and
 * the paid orders. The orders are placed in June 2001, a month the test database holds no
 * order of, so every figure is asserted as is.
 */
final class SalesReportExportsTest extends IntegrationTestCase
{
    private const string IN_THE_PERIOD = '2001-06-15 12:00:00';
    private const string IN_THE_PREVIOUS_PERIOD = '2001-05-20 12:00:00';
    private const string AFTER_THE_PERIOD = '2001-07-01 00:00:00';

    private const array ROUNDING_CONFIG = [
        'order_rounding_mode',
        'last_legacy_rounding_order_id',
        'last_sum_of_roundings_order_id',
        'last_text_promo_price_order_id',
    ];

    private FixtureFactory $factory;

    private TaxRule $taxRule;

    private Currency $currency;

    /** @var array<string, string|null> */
    private array $roundingConfig = [];

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->taxRule = $this->factory->taxRule();
        $this->currency = $this->factory->currency();

        foreach (self::ROUNDING_CONFIG as $name) {
            $this->roundingConfig[$name] = ConfigQuery::read($name);
        }

        ConfigQuery::write('order_rounding_mode', (string) ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS);
        ConfigQuery::write('last_legacy_rounding_order_id', '0');
        ConfigQuery::write('last_sum_of_roundings_order_id', '0');
        ConfigQuery::write('last_text_promo_price_order_id', '0');
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach ($this->roundingConfig as $name => $value) {
            if (null !== $value) {
                ConfigQuery::write($name, $value);
            }
        }

        ConfigQuery::resetCache();

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, class-string<AbstractExport>}>
     */
    public static function theExports(): iterable
    {
        yield 'by category' => ['thelia.export.sales_by_category', SalesByCategoryExport::class];
        yield 'by product' => ['thelia.export.sales_by_product', SalesByProductExport::class];
        yield 'by customer' => ['thelia.export.sales_by_customer', SalesByCustomerExport::class];
    }

    /**
     * @param class-string<AbstractExport> $class
     */
    #[DataProvider('theExports')]
    public function testTheExportIsRegisteredInTheCatalogue(string $ref, string $class): void
    {
        $export = ExportQuery::create()->findOneByRef($ref);

        self::assertNotNull($export);
        self::assertSame($class, $export->getHandleClass());
        self::assertSame('thelia.export.reports', $export->getExportCategory()?->getRef());
        self::assertTrue($export->getHandleClassInstance()->useRangeDate());
        self::assertNotSame('', $export->setLocale('fr_FR')->getTitle());
        self::assertNotSame('', $export->getDescription());
    }

    public function testTheCategoryFileHoldsTheRowsOfTheCalculation(): void
    {
        $parent = $this->category('Clothes');
        $shirts = $this->category('Shirts', $parent);
        $hats = $this->category('Hats', $parent);
        $this->product($shirts, 'SHIRT');
        $this->product($hats, 'HAT');
        $both = $this->paidOrder();
        $this->line($both, 'SHIRT', '30.000000', 1, '6.000000');
        $this->line($both, 'HAT', '10.000000', 2);
        $this->line($this->paidOrder(), 'SHIRT', '30.000000', 2, '6.000000');
        $this->line($this->paidOrder(['at' => self::IN_THE_PREVIOUS_PERIOD]), 'SHIRT', '30.000000', 1, '6.000000');

        $file = $this->csvRows(SalesByCategoryExport::class, $this->june());
        $calculated = (new SalesReportCalculator())->byCategory($this->criteria(), 'en_US');

        // The categories, then the discounts, the shipping costs and the total.
        self::assertCount(\count($calculated) + 3, $file);
        self::assertSame(['Clothes', 'Clothes > Shirts', 'Clothes > Hats'], \array_slice(array_column($file, 'Category'), 0, 3));

        foreach ($calculated as $index => $row) {
            self::assertEqualsWithDelta($row->revenue, (float) $file[$index]['Revenue incl. tax'], 0.005);
            self::assertEqualsWithDelta($row->previousRevenue, (float) $file[$index]['Previous period revenue incl. tax'], 0.005);
            self::assertSame((string) $row->quantity, $file[$index]['Quantity']);
            self::assertSame((string) $row->orderCount, $file[$index]['Orders']);
            self::assertEqualsWithDelta($row->share * 100, (float) $file[$index]['Share of the revenue (%)'], 0.01);
        }

        // 108 now against 36 before: +200 %.
        self::assertEqualsWithDelta(200.0, (float) $file[1]['Change from the previous period (%)'], 0.005);
    }

    public function testTheLinesOfReconciliationBringTheCategoriesBackToTheTotal(): void
    {
        $parent = $this->category('Clothes');
        $child = $this->category('Shirts', $parent);
        $other = $this->category('Hats');
        $this->product($child, 'SHIRT');
        $this->product($other, 'HAT');
        $order = $this->paidOrder(['postage' => '7.000000']);
        $this->line($order, 'SHIRT', '30.000000', 2, '6.000000');
        $this->line($order, 'HAT', '10.000000', 1);
        $order->setDiscount('5.000000')->save($this->getPropelConnection());
        $this->line($this->paidOrder(), 'GONE-PRODUCT', '4.000000', 1);

        $file = $this->csvRows(SalesByCategoryExport::class, $this->june());

        $total = array_pop($file);
        $shipping = array_pop($file);
        $discounts = array_pop($file);
        self::assertSame(['Discounts', 'Shipping costs', 'Total'], [$discounts['Category'], $shipping['Category'], $total['Category']]);
        self::assertEqualsWithDelta(-5.0, (float) $discounts['Revenue incl. tax'], 0.005);
        self::assertEqualsWithDelta(7.0, (float) $shipping['Revenue incl. tax'], 0.005);

        $roots = array_filter($file, static fn (array $row): bool => !str_contains($row['Category'], ' > '));
        self::assertGreaterThanOrEqual(3, \count($roots), 'Clothes, Hats and the uncategorized goods.');
        self::assertEqualsWithDelta(
            (float) $total['Revenue incl. tax'],
            array_sum(array_map(static fn (array $row): float => (float) $row['Revenue incl. tax'], $roots)) + (float) $discounts['Revenue incl. tax'] + (float) $shipping['Revenue incl. tax'],
            0.01,
        );
        // 72 + 10 + 4 of goods, less 5 of discount, plus 7 of shipping.
        self::assertEqualsWithDelta(88.0, (float) $total['Revenue incl. tax'], 0.005);
        self::assertSame('2', $total['Orders']);
    }

    public function testTheProductFileListsEveryProductSoldAcrossThePages(): void
    {
        $order = $this->paidOrder();

        for ($i = 1; $i <= 501; ++$i) {
            $this->line($order, \sprintf('BULK-%03d', $i), '1.000000', 1);
        }

        $file = $this->csvRows(SalesByProductExport::class, $this->june());

        self::assertCount(501, $file);
        self::assertCount(501, array_unique(array_column($file, 'Product reference')));
        self::assertSame(['Product reference', 'Product', 'Revenue incl. tax', 'Previous period revenue incl. tax', 'Change from the previous period (%)', 'Quantity', 'Share of the revenue (%)'], array_keys($file[0]));
    }

    public function testTheProductFileHoldsTheRowsOfTheCalculationBestSellerFirst(): void
    {
        $category = $this->category('Clothes');
        $this->product($category, 'CHEAP', 'A cheap one');
        $this->product($category, 'DEAR', 'A dear one');
        $order = $this->paidOrder();
        $this->line($order, 'CHEAP', '5.000000', 3);
        $this->line($order, 'DEAR', '50.000000', 1);
        $this->line($this->paidOrder(['at' => self::IN_THE_PREVIOUS_PERIOD]), 'DEAR', '25.000000', 1);

        $file = $this->csvRows(SalesByProductExport::class, $this->june());
        $calculated = (new SalesReportCalculator())->byProduct($this->criteria(), 'en_US', 1, 500)->rows;

        self::assertSame(['DEAR', 'CHEAP'], array_column($file, 'Product reference'));
        self::assertSame(array_map(static fn ($row): string => $row->ref, $calculated), array_column($file, 'Product reference'));
        self::assertSame(['A dear one', 'A cheap one'], array_column($file, 'Product'));
        self::assertEqualsWithDelta(50.0, (float) $file[0]['Revenue incl. tax'], 0.005);
        self::assertEqualsWithDelta(25.0, (float) $file[0]['Previous period revenue incl. tax'], 0.005);
        self::assertEqualsWithDelta(100.0, (float) $file[0]['Change from the previous period (%)'], 0.005);
        self::assertSame('', $file[1]['Change from the previous period (%)'], 'Nothing sold before: no change to state.');
        self::assertSame('3', $file[1]['Quantity']);
    }

    public function testTheCustomerFileHoldsTheRowsOfTheCalculationBiggestTotalFirst(): void
    {
        $small = $this->customer('Small');
        $big = $this->customer('Big');
        $this->line($this->paidOrder(['customer' => $small]), 'ANY', '10.000000', 1);
        $this->line($this->paidOrder(['customer' => $big]), 'ANY', '40.000000', 1);
        $this->line($this->paidOrder(['customer' => $big]), 'ANY', '20.000000', 1);
        $this->line($this->paidOrder(['customer' => $big, 'at' => self::IN_THE_PREVIOUS_PERIOD]), 'ANY', '30.000000', 1);

        $file = $this->csvRows(SalesByCustomerExport::class, $this->june());
        $calculated = (new SalesReportCalculator())->byCustomer($this->criteria(), CustomerSort::Total, true, 1, 500)->rows;

        self::assertSame(array_map(static fn ($row): string => $row->ref, $calculated), array_column($file, 'Customer reference'));
        self::assertSame([$big->getRef(), $small->getRef()], array_column($file, 'Customer reference'));
        self::assertSame('2', $file[0]['Orders']);
        self::assertEqualsWithDelta(60.0, (float) $file[0]['Total incl. tax'], 0.005);
        self::assertEqualsWithDelta(30.0, (float) $file[0]['Average basket incl. tax'], 0.005);
        self::assertEqualsWithDelta(30.0, (float) $file[0]['Previous period total incl. tax'], 0.005);
        self::assertEqualsWithDelta(100.0, (float) $file[0]['Change from the previous period (%)'], 0.005);
        self::assertSame(self::IN_THE_PERIOD, $file[0]['Last order']);
        self::assertSame($big->getEmail(), $file[0]['Email address']);
    }

    public function testTheSummaryOfTheCategoryReportTellsTheRevenueTheChangeAndTheTopCategory(): void
    {
        $category = $this->category('Clothes');
        $this->product($category, 'SHIRT');
        $this->line($this->paidOrder(), 'SHIRT', '30.000000', 1);
        $this->line($this->paidOrder(['at' => self::IN_THE_PREVIOUS_PERIOD]), 'SHIRT', '10.000000', 1);

        $lines = $this->summaryOf(SalesByCategoryExport::class, $this->june());

        self::assertSame(['Revenue incl. tax', 'Change from the previous period', 'Top category'], array_column($lines, 0));
        self::assertStringContainsString('30', $lines[0][1]);
        self::assertSame('+200.00 %', $lines[1][1]);
        self::assertStringStartsWith('Clothes (', $lines[2][1]);
    }

    public function testTheSummaryOfTheProductAndCustomerReportsNameTheBestOne(): void
    {
        $customer = $this->customer('Jane');
        $this->product($this->category('Clothes'), 'SHIRT', 'A shirt');
        $this->line($this->paidOrder(['customer' => $customer]), 'SHIRT', '30.000000', 1);

        $product = $this->summaryOf(SalesByProductExport::class, $this->june());
        $buyer = $this->summaryOf(SalesByCustomerExport::class, $this->june());

        self::assertSame(['Revenue incl. tax', 'Top product'], array_column($product, 0), 'Nothing sold before: no change to state.');
        self::assertStringStartsWith('A shirt (', $product[1][1]);
        self::assertSame(['Revenue incl. tax', 'Top customer'], array_column($buyer, 0));
        self::assertStringStartsWith($customer->getFirstname().' '.$customer->getLastname().' (', $buyer[1][1]);
    }

    public function testOnlyTheOrdersOfThePeriodOfTheJobAreExported(): void
    {
        $this->line($this->paidOrder(), 'IN-JUNE', '10.000000', 1);
        $this->line($this->paidOrder(['at' => self::AFTER_THE_PERIOD]), 'IN-JULY', '10.000000', 1);
        $this->line($this->paidOrder(['at' => self::IN_THE_PREVIOUS_PERIOD]), 'IN-JUNE', '10.000000', 1);
        $this->line($this->paidOrder(['at' => self::IN_THE_PREVIOUS_PERIOD]), 'ONLY-IN-MAY', '10.000000', 1);

        $june = $this->csvRows(SalesByProductExport::class, $this->june());
        $fromJune = $this->csvRows(SalesByProductExport::class, ['start' => new \DateTime('2001-06-01 00:00:00'), 'end' => null]);

        self::assertSame(['IN-JUNE'], array_column($june, 'Product reference'));
        self::assertNotContains('ONLY-IN-MAY', array_column($june, 'Product reference'));
        self::assertEqualsWithDelta(10.0, (float) $june[0]['Previous period revenue incl. tax'], 0.005, 'The previous period is the month before.');
        self::assertContains('IN-JUNE', array_column($fromJune, 'Product reference'));
        self::assertContains('IN-JULY', array_column($fromJune, 'Product reference'), 'Without an end the period runs up to today.');
        self::assertNotContains('ONLY-IN-MAY', array_column($fromJune, 'Product reference'));
    }

    public function testWithoutAPeriodTheExportStartsOnTheFirstOrderOfTheShop(): void
    {
        $this->line($this->paidOrder(['at' => '1999-01-02 10:00:00']), 'VERY-OLD', '10.000000', 1);

        $file = $this->csvRows(SalesByProductExport::class, null);

        self::assertContains('VERY-OLD', array_column($file, 'Product reference'));
    }

    public function testOnlyThePaidOrdersAreExported(): void
    {
        $this->forgetTheStatusLists();
        $this->line($this->paidOrder(), 'PAID', '10.000000', 1);
        $this->line($this->paidOrder(['status' => OrderStatus::CODE_NOT_PAID]), 'UNPAID', '10.000000', 1);

        self::assertSame(['PAID'], array_column($this->csvRows(SalesByProductExport::class, $this->june()), 'Product reference'));
    }

    public function testAPeriodWithoutSalesHasNoData(): void
    {
        $this->expectException(DataTransferNoDataFoundException::class);

        $this->csvRows(SalesByProductExport::class, ['start' => new \DateTime('1990-01-01 00:00:00'), 'end' => new \DateTime('1990-01-31 23:59:59')]);
    }

    public function testAPeriodEndingBeforeItStartsHasNoData(): void
    {
        $this->expectException(DataTransferNoDataFoundException::class);

        $this->csvRows(SalesByCustomerExport::class, ['start' => new \DateTime('2001-07-01 00:00:00'), 'end' => new \DateTime('2001-06-01 00:00:00')]);
    }

    public function testWhatACustomerOrAnAdministratorTypedIsWrittenAsText(): void
    {
        $customer = $this->customer('=1+2');
        $customer->setLastname('@SUM(1+1)')->save($this->getPropelConnection());
        $category = $this->category('=HYPERLINK("http://example.com")');
        $this->product($category, 'FORMULA', '-1+cmd');
        $this->line($this->paidOrder(['customer' => $customer]), 'FORMULA', '10.000000', 1);
        $this->line($this->paidOrder(), '+cmd', '10.000000', 1, null, ['title' => '=1+2']);

        $categories = $this->csvRows(SalesByCategoryExport::class, $this->june());
        $products = $this->csvRows(SalesByProductExport::class, $this->june());
        $customers = $this->csvRows(SalesByCustomerExport::class, $this->june());

        self::assertContains("'=HYPERLINK(\"http://example.com\")", array_column($categories, 'Category'));
        self::assertContains("'-1+cmd", array_column($products, 'Product'));
        self::assertContains("'+cmd", array_column($products, 'Product reference'));
        self::assertContains("'=1+2", array_column($customers, 'First name'));
        self::assertContains("'@SUM(1+1)", array_column($customers, 'Last name'));
    }

    /**
     * @param array{start: \DateTime|null, end: \DateTime|null}|null $range
     *
     * @return list<array<string, string>> the rows of the CSV file, by their header
     */
    private function csvRows(string $handleClass, ?array $range): array
    {
        $export = (new Export())->setRef('sales-report-test')->setHandleClass($handleClass);

        $event = $this->getService(ExportHandler::class)->export(
            $export,
            $this->getService(SerializerManager::class)->get('thelia.csv'),
            null,
            LangQuery::create()->findOneByLocale('en_US'),
            false,
            false,
            $range,
        );
        $this->files[] = $event->getFilePath();

        $handle = fopen($event->getFilePath(), 'r');
        $headers = fgetcsv($handle, null, ',', '"', '');
        $rows = [];

        while (false !== ($row = fgetcsv($handle, null, ',', '"', ''))) {
            $rows[] = array_combine($headers, $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param array{start: \DateTime|null, end: \DateTime|null}|null $range
     *
     * @return list<array{string, string}>
     */
    private function summaryOf(string $handleClass, ?array $range): array
    {
        $export = new $handleClass();
        $export->setLang(LangQuery::create()->findOneByLocale('en_US'));
        $export->setRangeDate($range);

        return array_map(static fn ($line): array => [$line->label, $line->value], $export->summarize());
    }

    /**
     * @return array{start: \DateTime, end: \DateTime}
     */
    private function june(): array
    {
        return ['start' => new \DateTime('2001-06-01 00:00:00'), 'end' => new \DateTime('2001-06-30 23:59:59')];
    }

    private function criteria(): SalesReportCriteria
    {
        return new SalesReportCriteria(new \DateTimeImmutable('2001-06-01 00:00:00'), new \DateTimeImmutable('2001-06-30 23:59:59'), StatusScope::Paid);
    }

    private function category(string $title, ?Category $parent = null): Category
    {
        $category = $this->factory->category(['parent' => $parent?->getId() ?? 0]);
        $category->setLocale('en_US')->setTitle($title)->save($this->getPropelConnection());

        return $category;
    }

    private function product(Category $category, string $ref, ?string $title = null): Product
    {
        return $this->factory->product($category, $this->taxRule, $this->currency, ['ref' => $ref, 'title' => $title ?? $ref]);
    }

    private function customer(string $firstname): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), ['firstname' => $firstname]);
    }

    /**
     * @param array<string, mixed> $overrides `at`, `customer`, `postage`, `status`
     */
    private function paidOrder(array $overrides = []): Order
    {
        $customer = $overrides['customer'] ?? null;
        \assert(null === $customer || $customer instanceof Customer);

        $order = $this->factory->order($customer, [
            'statusCode' => $overrides['status'] ?? OrderStatus::CODE_PAID,
            'postage' => $overrides['postage'] ?? '0.000000',
        ]);
        $order->setCreatedAt(new \DateTime($overrides['at'] ?? self::IN_THE_PERIOD));
        $order->save($this->getPropelConnection());

        return $order;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function line(Order $order, string $ref, string $price, float $quantity, ?string $tax = null, array $overrides = []): OrderProduct
    {
        $line = $this->factory->orderProduct($order, ['productRef' => $ref, 'price' => $price, 'promoPrice' => $price, 'quantity' => $quantity] + $overrides);

        if (null !== $tax) {
            (new \Thelia\Model\OrderProductTax())
                ->setOrderProductId($line->getId())
                ->setTitle('VAT')
                ->setDescription('')
                ->setAmount($tax)
                ->setPromoAmount($tax)
                ->save($this->getPropelConnection());
        }

        return $line;
    }

    private function forgetTheStatusLists(): void
    {
        (new \ReflectionProperty(OrderStatusQuery::class, 'statusIdListsCache'))->setValue(null, []);
    }
}
