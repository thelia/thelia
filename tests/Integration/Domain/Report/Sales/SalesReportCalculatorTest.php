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

namespace Thelia\Tests\Integration\Domain\Report\Sales;

use Thelia\Domain\Report\Sales\CategoryRowKind;
use Thelia\Domain\Report\Sales\CategorySalesRow;
use Thelia\Domain\Report\Sales\CustomerSalesRow;
use Thelia\Domain\Report\Sales\CustomerSort;
use Thelia\Domain\Report\Sales\ProductSalesRow;
use Thelia\Domain\Report\Sales\SalesReportCalculator;
use Thelia\Domain\Report\Sales\SalesReportCriteria;
use Thelia\Domain\Report\Sales\StatusScope;
use Thelia\Model\Category;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\TaxRule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The sales of a period by category, by product and by customer. The orders are placed in
 * June 2001, a month the test database holds no order of, so every figure is asserted as is.
 */
final class SalesReportCalculatorTest extends IntegrationTestCase
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

    private SalesReportCalculator $calculator;

    private FixtureFactory $factory;

    private TaxRule $taxRule;

    private Currency $currency;

    /** @var array<string, string|null> */
    private array $roundingConfig = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new SalesReportCalculator($this->getPropelConnection());
        $this->factory = $this->createFixtureFactory();
        $this->taxRule = $this->factory->taxRule();
        $this->currency = $this->factory->currency();

        foreach (self::ROUNDING_CONFIG as $name) {
            $this->roundingConfig[$name] = ConfigQuery::read($name);
        }

        // The pivots of the shop under test are below every order the test creates: each
        // order follows the rule of the shop unless the test freezes it.
        ConfigQuery::write('order_rounding_mode', (string) ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS);
        ConfigQuery::write('last_legacy_rounding_order_id', '0');
        ConfigQuery::write('last_sum_of_roundings_order_id', '0');
        ConfigQuery::write('last_text_promo_price_order_id', '0');
    }

    protected function tearDown(): void
    {
        foreach ($this->roundingConfig as $name => $value) {
            if (null !== $value) {
                ConfigQuery::write($name, $value);
            }
        }

        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testACategorySumsItsSubcategoriesAndCountsAnOrderTouchingTwoOfThemOnce(): void
    {
        $parent = $this->category('Clothes');
        $shirts = $this->category('Shirts', $parent);
        $hats = $this->category('Hats', $parent);
        $shirt = $this->product($shirts, 'SHIRT');
        $hat = $this->product($hats, 'HAT');

        $both = $this->paidOrder();
        $this->line($both, 'SHIRT', '30.000000', 1, '6.000000');
        $this->line($both, 'HAT', '10.000000', 2);
        $shirtOnly = $this->paidOrder();
        $this->line($shirtOnly, 'SHIRT', '30.000000', 2, '6.000000');

        $rows = $this->calculator->byCategory($this->june(), 'en_US');

        self::assertSame(
            [[$parent->getId(), null, 0, 128.0, 0.0, 5, 2], [$shirts->getId(), $parent->getId(), 1, 108.0, 108.0, 3, 2], [$hats->getId(), $parent->getId(), 1, 20.0, 20.0, 2, 1]],
            array_map(static fn (CategorySalesRow $row): array => [$row->categoryId, $row->parentId, $row->depth, $row->revenue, $row->ownRevenue, $row->quantity, $row->orderCount], $rows),
            'The parent first, then its children by revenue; an order touching both children is one order of the parent.',
        );
        self::assertSame(['Clothes', 'Shirts', 'Hats'], array_map(static fn (CategorySalesRow $row): string => $row->title, $rows));
        self::assertEqualsWithDelta(1.0, $rows[0]->share, 0.000001);
        self::assertEqualsWithDelta(108 / 128, $rows[1]->share, 0.000001);
        self::assertNotNull($shirt->getId());
        self::assertNotNull($hat->getId());
    }

    public function testAProductFiledInSeveralCategoriesIsCountedInItsDefaultOneOnly(): void
    {
        $first = $this->category('First filed');
        $default = $this->category('Default');
        $product = $this->product($first, 'TWO-HOMES');
        $this->factory->productCategory($product, $default);
        ProductCategoryQuery::create()->filterByProductId($product->getId())->filterByCategoryId($first->getId())->update(['DefaultCategory' => false], $this->getPropelConnection());
        ProductCategoryQuery::create()->filterByProductId($product->getId())->filterByCategoryId($default->getId())->update(['DefaultCategory' => true], $this->getPropelConnection());

        $this->line($this->paidOrder(), 'TWO-HOMES', '15.000000', 1);

        $rows = $this->calculator->byCategory($this->june(), 'en_US');

        self::assertSame([$default->getId()], array_map(static fn (CategorySalesRow $row): ?int => $row->categoryId, $rows));
    }

    public function testADeletedProductIsUncategorizedAndAServiceIsAServiceListedLast(): void
    {
        $category = $this->category('Books');
        $this->product($category, 'BOOK');
        // The gift wrapping is a product of the catalogue too: its line is a service all the same.
        $this->product($this->category('Wrapping'), 'GIFT-WRAP');

        $order = $this->paidOrder();
        $this->line($order, 'BOOK', '8.000000', 1);
        $this->line($order, 'GONE-SINCE', '50.000000', 1, '10.000000', ['title' => 'A product deleted since']);
        $this->line($order, 'GIFT-WRAP', '2.000000', 1, '0.400000', ['lineType' => OrderProduct::LINE_TYPE_SERVICE]);

        $rows = $this->calculator->byCategory($this->june(), 'en_US');

        self::assertSame(
            [[CategoryRowKind::Category, $category->getId(), 8.0], [CategoryRowKind::Uncategorized, null, 60.0], [CategoryRowKind::Services, null, 2.4]],
            array_map(static fn (CategorySalesRow $row): array => [$row->kind, $row->categoryId, $row->revenue], $rows),
        );
        self::assertSame('Uncategorized', $rows[1]->title);

        $products = $this->calculator->byProduct($this->june(), 'en_US');
        $gone = $this->productRow($products->rows, 'GONE-SINCE');
        self::assertNull($gone->productId);
        self::assertSame('A product deleted since', $gone->title, 'A product deleted since keeps the title its order line stored.');
    }

    public function testTheSalesOfACategoryDeletedSinceStayInTheRevenueAsUncategorized(): void
    {
        $kept = $this->category('Kept');
        $this->product($kept, 'KEPT');
        $closed = $this->category('Closed since');
        $this->product($closed, 'ORPHAN');

        $order = $this->paidOrder();
        $this->line($order, 'KEPT', '10.000000', 1);
        $this->line($order, 'ORPHAN', '30.000000', 1);
        $before = $this->calculator->reconciliation($this->june());

        $closed->delete($this->getPropelConnection());

        $rows = $this->calculator->byCategory($this->june(), 'en_US');
        self::assertSame(
            [[CategoryRowKind::Category, $kept->getId(), 10.0], [CategoryRowKind::Uncategorized, null, 30.0]],
            array_map(static fn (CategorySalesRow $row): array => [$row->kind, $row->categoryId, $row->revenue], $rows),
        );
        self::assertEqualsWithDelta($before->linesTotal, $this->calculator->reconciliation($this->june())->linesTotal, 0.000001, 'Deleting a category takes nothing out of the revenue.');
    }

    public function testTheComponentsOfABundleAreNotCountedAsSold(): void
    {
        $packs = $this->category('Packs');
        $parts = $this->category('Parts');
        $this->product($packs, 'PACK');
        $this->product($parts, 'PART');

        $order = $this->paidOrder();
        $this->line($order, 'PACK', '50.000000', 2, '10.000000', ['lineType' => OrderProduct::LINE_TYPE_BUNDLE]);
        $this->line($order, 'PART', '0.000000', 6, null, ['lineType' => OrderProduct::LINE_TYPE_BUNDLE_COMPONENT]);
        $this->line($this->paidOrder(), 'PART', '5.000000', 1);

        $rows = $this->calculator->byCategory($this->june(), 'en_US');

        self::assertSame(
            [[$packs->getId(), 120.0, 2, 1], [$parts->getId(), 5.0, 1, 1]],
            array_map(static fn (CategorySalesRow $row): array => [$row->categoryId, $row->revenue, $row->quantity, $row->orderCount], $rows),
            'The pack is sold, its components are what it is made of: only the part sold on its own counts.',
        );
        self::assertSame(
            [['PACK', 2], ['PART', 1]],
            array_map(static fn (ProductSalesRow $row): array => [$row->ref, $row->quantity], $this->calculator->byProduct($this->june(), 'en_US')->rows),
        );
    }

    /**
     * Four orders, each charged with another rule: frozen by the 2.4 upgrade (no rounding), placed while the
     * promotional price was a text, placed before the shop switched to rounding of sums, and placed after it.
     */
    public function testEachOrderIsTotalledByTheRoundingRuleItWasChargedWith(): void
    {
        $category = $this->category('Bulk');
        foreach (['LEGACY', 'TEXT', 'SUMS', 'ROUNDED'] as $ref) {
            $this->product($category, $ref);
        }

        $legacy = $this->bulkOrder('LEGACY');
        $text = $this->paidOrder();
        $promotional = $this->line($text, 'TEXT', '66.658333', 1, null);
        $promotional->setWasInPromo(1)->setPromoPrice('63.325000')->save($this->getPropelConnection());
        $this->tax($promotional, '13.331667', '12.665000');
        $sumOfRoundings = $this->bulkOrder('SUMS');

        ConfigQuery::write('last_legacy_rounding_order_id', (string) $legacy->getId());
        ConfigQuery::write('last_text_promo_price_order_id', (string) $text->getId());
        ConfigQuery::write('last_sum_of_roundings_order_id', (string) $sumOfRoundings->getId());
        ConfigQuery::write('order_rounding_mode', (string) ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS);

        $roundingOfSums = $this->bulkOrder('ROUNDED');

        $expected = ['LEGACY' => [$legacy, 1.797], 'TEXT' => [$text, 75.99], 'SUMS' => [$sumOfRoundings, 3.0], 'ROUNDED' => [$roundingOfSums, 1.80]];
        $products = $this->calculator->byProduct($this->june(), 'en_US');

        foreach ($expected as $ref => [$order, $amount]) {
            self::assertEqualsWithDelta($amount, $this->goodsOf($order), 0.000001, $ref.': the fixture is not the case it stands for.');
            self::assertEqualsWithDelta($amount, $this->productRow($products->rows, $ref)->revenue, 0.000001, $ref);
        }

        self::assertEqualsWithDelta(1.797 + 75.99 + 3.0 + 1.80, $this->calculator->byCategory($this->june(), 'en_US')[0]->revenue, 0.000001);
    }

    /**
     * A price stored with six decimals, three times: 3 x (1.00 + 0.20) when the unit amounts are rounded,
     * ROUND(3 x 1.205998) when the line total is.
     */
    public function testAPriceWithSixDecimalsBoughtThreeTimesIsRoundedWhereTheRuleRoundsIt(): void
    {
        $this->product($this->category('Six decimals'), 'SIX');

        $sums = $this->paidOrder();
        $this->line($sums, 'SIX', '1.004999', 3, '0.200999');
        ConfigQuery::write('order_rounding_mode', (string) ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS);
        ConfigQuery::write('last_sum_of_roundings_order_id', (string) $sums->getId());
        $rounded = $this->paidOrder();
        $this->line($rounded, 'SIX', '1.004999', 3, '0.200999');

        self::assertEqualsWithDelta(3.60, $this->goodsOf($sums), 0.000001);
        self::assertEqualsWithDelta(3.62, $this->goodsOf($rounded), 0.000001);
        self::assertEqualsWithDelta(7.22, $this->productRow($this->calculator->byProduct($this->june(), 'en_US')->rows, 'SIX')->revenue, 0.000001);
    }

    public function testADiscountLargerThanTheGoodsLeavesThePostage(): void
    {
        $this->product($this->category('Cheap'), 'CHEAP');
        $order = $this->paidOrder(['postage' => '5.000000']);
        $order->setDiscount('50.000000')->save($this->getPropelConnection());
        $this->line($order, 'CHEAP', '20.000000', 1);

        $reconciliation = $this->calculator->reconciliation($this->june());

        self::assertEqualsWithDelta(5.0, $order->getTotalAmount(), 0.000001);
        self::assertSame([20.0, 20.0, 5.0, 5.0, 1], [$reconciliation->linesTotal, $reconciliation->discounts, $reconciliation->postage, $reconciliation->revenue, $reconciliation->orderCount]);
        self::assertEqualsWithDelta(5.0, $this->calculator->byCustomer($this->june())->rows[0]->total, 0.000001);
    }

    /**
     * The figure the report stands on: the categories share out every line of the paid orders of the period, and
     * once the discounts are taken off and the postage added, they make the revenue the orders were charged.
     */
    public function testTheCategoriesLessTheDiscountsPlusThePostageMakeTheRevenueOfThePaidOrders(): void
    {
        $paid = $this->monthOfOrders();

        $rows = $this->calculator->byCategory($this->june(), 'en_US');
        $topLevel = array_sum(array_map(static fn (CategorySalesRow $row): float => null === $row->parentId ? $row->revenue : 0.0, $rows));
        $reconciliation = $this->calculator->reconciliation($this->june());

        $charged = array_sum(array_map(static fn (Order $order): float => $order->getTotalAmount(), $paid));
        $discounts = array_sum(array_map(fn (Order $order): float => min((float) $order->getDiscount(), $this->goodsOf($order)), $paid));
        $postage = array_sum(array_map(static fn (Order $order): float => (float) $order->getPostage(), $paid));

        self::assertGreaterThan(0.0, $discounts);
        self::assertEqualsWithDelta($charged, $topLevel - $discounts + $postage, 0.001);
        self::assertEqualsWithDelta($charged, $reconciliation->revenue, 0.001);
        self::assertEqualsWithDelta($topLevel, $reconciliation->linesTotal, 0.001);
        self::assertSame(\count($paid), $reconciliation->orderCount);
        self::assertEqualsWithDelta(
            $charged,
            array_sum(array_map(static fn (CustomerSalesRow $row): float => $row->total, $this->calculator->byCustomer($this->june(), perPage: 500)->rows)),
            0.001,
        );
        self::assertEqualsWithDelta(1.0, array_sum(array_map(static fn (CategorySalesRow $row): float => null === $row->parentId ? $row->share : 0.0, $rows)), 0.000001);
    }

    public function testAStatusOfTheShopStandingForPaidIsPaidAndTheAllScopeTakesEveryOrder(): void
    {
        $this->product($this->category('Status'), 'STATUS');
        $status = $this->factory->orderStatus(['code' => 'cashed-at-the-counter', 'equivalentCode' => OrderStatus::CODE_PAID]);
        $this->forgetTheStatusLists();

        $this->line($this->paidOrder(['status' => (string) $status->getCode()]), 'STATUS', '10.000000', 1);
        $this->line($this->paidOrder(['status' => OrderStatus::CODE_SENT]), 'STATUS', '20.000000', 1);
        $this->line($this->paidOrder(['status' => OrderStatus::CODE_NOT_PAID]), 'STATUS', '40.000000', 1);
        $this->line($this->paidOrder(['status' => OrderStatus::CODE_CANCELED]), 'STATUS', '80.000000', 1);

        self::assertSame(30.0, $this->productRow($this->calculator->byProduct($this->june(), 'en_US')->rows, 'STATUS')->revenue);
        self::assertSame(150.0, $this->productRow($this->calculator->byProduct($this->june(StatusScope::All), 'en_US')->rows, 'STATUS')->revenue);
        self::assertSame(2, $this->calculator->reconciliation($this->june())->orderCount);
        self::assertSame(4, $this->calculator->reconciliation($this->june(StatusScope::All))->orderCount);
    }

    public function testTheSaleUnitsNarrowTheOrdersToTheOnesPlacedOnThem(): void
    {
        $this->product($this->category('Units'), 'UNIT');
        $north = $this->factory->saleUnit();
        $south = $this->factory->saleUnit();

        $this->line($this->paidOrder(['saleUnit' => $north->getId()]), 'UNIT', '1.000000', 1);
        $this->line($this->paidOrder(['saleUnit' => $south->getId()]), 'UNIT', '10.000000', 1);
        $this->line($this->paidOrder(), 'UNIT', '100.000000', 1);

        $revenue = fn (?array $saleUnitIds): float => $this->calculator->reconciliation($this->june(StatusScope::Paid, $saleUnitIds))->linesTotal;

        self::assertSame(111.0, $revenue(null));
        self::assertSame(1.0, $revenue([(int) $north->getId()]));
        self::assertSame(11.0, $revenue([(int) $north->getId(), (int) $south->getId()]), 'A national order is not placed on any of the units.');
        self::assertSame(0.0, $revenue([]));
    }

    public function testEveryFigureIsComparedWithThePreviousPeriod(): void
    {
        $category = $this->category('Compared');
        $this->product($category, 'COMPARED');
        $customer = $this->factory->customer($this->factory->customerTitle());

        $this->line($this->paidOrder(['customer' => $customer]), 'COMPARED', '10.000000', 1);
        $this->line($this->paidOrder(['customer' => $customer, 'at' => self::IN_THE_PREVIOUS_PERIOD]), 'COMPARED', '4.000000', 1);
        $this->line($this->paidOrder(['customer' => $customer, 'at' => self::AFTER_THE_PERIOD]), 'COMPARED', '1000.000000', 1);

        self::assertSame([10.0, 4.0], $this->figures($this->calculator->byCategory($this->june(), 'en_US')[0], 'revenue', 'previousRevenue'));
        self::assertSame([10.0, 4.0], $this->figures($this->productRow($this->calculator->byProduct($this->june(), 'en_US')->rows, 'COMPARED'), 'revenue', 'previousRevenue'));

        $row = $this->calculator->byCustomer($this->june())->rows[0];
        self::assertSame([10.0, 4.0, 1], $this->figures($row, 'total', 'previousTotal', 'orderCount'));
        self::assertSame(self::IN_THE_PERIOD, $row->lastOrderAt->format('Y-m-d H:i:s'), 'The last order is the last one of the period.');
    }

    public function testTheCustomersArePagedAndSortedOnTheColumnAsked(): void
    {
        $this->product($this->category('Customers'), 'CUST');
        $title = $this->factory->customerTitle();
        $alice = $this->factory->customer($title, ['firstname' => 'Alice', 'lastname' => 'Zola']);
        $bruno = $this->factory->customer($title, ['firstname' => 'Bruno', 'lastname' => 'Abel']);
        $chloe = $this->factory->customer($title, ['firstname' => 'Chloe', 'lastname' => 'Martin']);

        $this->line($this->paidOrder(['customer' => $alice, 'at' => '2001-06-01 08:00:00']), 'CUST', '100.000000', 1);
        $this->line($this->paidOrder(['customer' => $bruno, 'at' => '2001-06-02 08:00:00']), 'CUST', '30.000000', 1);
        $this->line($this->paidOrder(['customer' => $bruno, 'at' => '2001-06-20 08:00:00']), 'CUST', '30.000000', 1);
        $this->line($this->paidOrder(['customer' => $chloe, 'at' => '2001-06-10 08:00:00']), 'CUST', '60.000000', 1);

        $byTotal = $this->calculator->byCustomer($this->june(), CustomerSort::Total, true, 1, 2);
        self::assertSame([3, 1, 2, 2], [$byTotal->totalRows, $byTotal->page, $byTotal->perPage, $byTotal->pageCount()]);
        self::assertSame([$alice->getId(), $bruno->getId()], $this->customerIds($byTotal->rows), 'Bruno and Chloe spent 60 each: the lower id comes first.');
        self::assertSame([$chloe->getId()], $this->customerIds($this->calculator->byCustomer($this->june(), CustomerSort::Total, true, 2, 2)->rows));

        self::assertSame([$bruno->getId(), $chloe->getId(), $alice->getId()], $this->customerIds($this->calculator->byCustomer($this->june(), CustomerSort::Name, false)->rows));
        self::assertSame([$bruno->getId(), $alice->getId(), $chloe->getId()], $this->customerIds($this->calculator->byCustomer($this->june(), CustomerSort::OrderCount)->rows));
        self::assertSame([$bruno->getId(), $chloe->getId(), $alice->getId()], $this->customerIds($this->calculator->byCustomer($this->june(), CustomerSort::AverageBasket, false)->rows));
        self::assertSame([$bruno->getId(), $chloe->getId(), $alice->getId()], $this->customerIds($this->calculator->byCustomer($this->june(), CustomerSort::LastOrder)->rows));

        $bruno = $this->calculator->byCustomer($this->june(), CustomerSort::OrderCount)->rows[0];
        self::assertSame([2, 60.0, 30.0], $this->figures($bruno, 'orderCount', 'total', 'averageBasket'));

        $beyond = $this->calculator->byCustomer($this->june(), CustomerSort::Total, true, 9, 2);
        self::assertSame([[], 3], [$beyond->rows, $beyond->totalRows], 'A page beyond the last one is empty but still knows the rows.');

        $farthest = $this->calculator->byCustomer($this->june(), CustomerSort::Total, true, \PHP_INT_MAX, 500);
        self::assertSame([[], 3], [$farthest->rows, $farthest->totalRows], 'The largest page asked for is an empty page, not an offset the database refuses.');
    }

    public function testTheProductsArePagedByRevenueThenReferenceWithinBounds(): void
    {
        $category = $this->category('Paged');
        foreach (['P-B', 'P-A', 'P-C'] as $ref) {
            $this->product($category, $ref, $ref.' title');
        }

        $order = $this->paidOrder();
        $this->line($order, 'P-B', '10.000000', 1);
        $this->line($order, 'P-A', '10.000000', 1);
        $this->line($order, 'P-C', '30.000000', 1);

        $first = $this->calculator->byProduct($this->june(), 'en_US', 1, 2);
        self::assertSame(['P-C', 'P-A'], array_map(static fn (ProductSalesRow $row): string => $row->ref, $first->rows));
        self::assertSame([3, 2], [$first->totalRows, $first->pageCount()]);
        self::assertSame('P-C title', $first->rows[0]->title);
        self::assertEqualsWithDelta(0.6, $first->rows[0]->share, 0.000001);

        self::assertSame(['P-B'], array_map(static fn (ProductSalesRow $row): string => $row->ref, $this->calculator->byProduct($this->june(), 'en_US', 2, 2)->rows));

        $bounded = $this->calculator->byProduct($this->june(), 'en_US', 0, 0);
        self::assertSame([1, 1, 1], [$bounded->page, $bounded->perPage, \count($bounded->rows)]);
        self::assertSame(500, $this->calculator->byProduct($this->june(), 'en_US', 1, 10_000)->perPage);

        $farthest = $this->calculator->byProduct($this->june(), 'en_US', \PHP_INT_MAX, 500);
        self::assertSame([[], 3], [$farthest->rows, $farthest->totalRows], 'The largest page asked for is an empty page, not an offset the database refuses.');
    }

    public function testATitleFallsBackOnAnotherLocaleThenOnTheId(): void
    {
        $french = $this->category('');
        $french->setLocale('fr_FR')->setTitle('Jardin')->save($this->getPropelConnection());
        $untitled = $this->factory->category();
        $this->product($french, 'GARDEN');
        $this->product($untitled, 'UNTITLED');

        $order = $this->paidOrder();
        $this->line($order, 'GARDEN', '20.000000', 1);
        $this->line($order, 'UNTITLED', '10.000000', 1);

        self::assertSame(['Jardin', '#'.$untitled->getId()], array_map(static fn (CategorySalesRow $row): string => $row->title, $this->calculator->byCategory($this->june(), 'en_US')));
    }

    /**
     * The paid orders of a busy June: two levels of categories, a product filed twice, a product deleted since,
     * a service, a bundle, a discount larger than its goods, postage, two rounding rules; and the orders the
     * report leaves out: an unpaid one, one placed the day after the period.
     *
     * @return list<Order>
     */
    private function monthOfOrders(): array
    {
        $root = $this->category('Root');
        $child = $this->category('Child', $root);
        $other = $this->category('Other');
        $this->product($child, 'M-CHILD');
        $twice = $this->product($root, 'M-TWICE');
        $this->factory->productCategory($twice, $other);
        $this->product($other, 'M-PACK');
        $this->product($child, 'M-PART');

        $first = $this->paidOrder(['postage' => '4.900000']);
        $this->line($first, 'M-CHILD', '12.345678', 3, '2.469136');
        $this->line($first, 'M-GONE', '7.500000', 1, '1.500000');
        $this->line($first, 'M-WRAP', '2.500000', 1, '0.500000', ['lineType' => OrderProduct::LINE_TYPE_SERVICE]);
        $first->setDiscount('3.330000')->save($this->getPropelConnection());

        $second = $this->paidOrder(['postage' => '0.000000']);
        $this->line($second, 'M-TWICE', '19.999999', 2, '4.000001');
        $this->line($second, 'M-PACK', '40.000000', 1, '8.000000', ['lineType' => OrderProduct::LINE_TYPE_BUNDLE]);
        $this->line($second, 'M-PART', '0.000000', 2, null, ['lineType' => OrderProduct::LINE_TYPE_BUNDLE_COMPONENT]);

        ConfigQuery::write('last_sum_of_roundings_order_id', (string) $second->getId());
        ConfigQuery::write('order_rounding_mode', (string) ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS);

        $third = $this->paidOrder(['postage' => '6.000000']);
        $this->line($third, 'M-CHILD', '1.004999', 3, '0.200999');
        $third->setDiscount('100.000000')->save($this->getPropelConnection());

        $this->line($this->paidOrder(['status' => OrderStatus::CODE_NOT_PAID]), 'M-CHILD', '500.000000', 1);
        $this->line($this->paidOrder(['at' => self::AFTER_THE_PERIOD]), 'M-CHILD', '900.000000', 1);

        return [$first, $second, $third];
    }

    /** @param list<int>|null $saleUnitIds */
    private function june(StatusScope $scope = StatusScope::Paid, ?array $saleUnitIds = null): SalesReportCriteria
    {
        return new SalesReportCriteria(
            new \DateTimeImmutable('2001-06-01 00:00:00'),
            new \DateTimeImmutable('2001-06-30 23:59:59'),
            $scope,
            $saleUnitIds,
        );
    }

    private function category(string $title, ?Category $parent = null): Category
    {
        $category = $this->factory->category(['parent' => $parent?->getId() ?? 0]);

        if ('' !== $title) {
            $category->setLocale('en_US')->setTitle($title)->save($this->getPropelConnection());
        }

        return $category;
    }

    private function product(Category $category, string $ref, ?string $title = null): Product
    {
        return $this->factory->product($category, $this->taxRule, $this->currency, ['ref' => $ref, 'title' => $title ?? $ref]);
    }

    /**
     * An order of the period, paid unless told otherwise. Overrides: `at` (the creation date), `customer`, `postage`,
     * `saleUnit` (an id), `status` (a status code).
     *
     * @param array<string, mixed> $overrides
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
        $order->setSaleUnitId($overrides['saleUnit'] ?? null);
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
            $this->tax($line, $tax);
        }

        return $line;
    }

    private function tax(OrderProduct $line, string $amount, ?string $promoAmount = null): void
    {
        (new OrderProductTax())
            ->setOrderProductId($line->getId())
            ->setTitle('VAT')
            ->setDescription('')
            ->setAmount($amount)
            ->setPromoAmount($promoAmount ?? $amount)
            ->save($this->getPropelConnection());
    }

    /** 300 g of a good sold by the gram: the case where the rounding rules land the furthest apart. */
    private function bulkOrder(string $ref): Order
    {
        $order = $this->paidOrder();
        $this->line($order, $ref, '0.005678', 300, '0.000312');

        return $order;
    }

    private function goodsOf(Order $order): float
    {
        $tax = 0.0;

        return $order->getTotalAmount($tax, false, false);
    }

    /**
     * @param list<ProductSalesRow> $rows
     */
    private function productRow(array $rows, string $ref): ProductSalesRow
    {
        foreach ($rows as $row) {
            if ($ref === $row->ref) {
                return $row;
            }
        }

        self::fail(\sprintf('No row for the product %s.', $ref));
    }

    /**
     * @param list<CustomerSalesRow> $rows
     *
     * @return list<int>
     */
    private function customerIds(array $rows): array
    {
        return array_map(static fn (CustomerSalesRow $row): int => $row->customerId, $rows);
    }

    /**
     * @return list<mixed>
     */
    private function figures(object $row, string ...$properties): array
    {
        return array_map(static fn (string $property): mixed => $row->{$property}, $properties);
    }

    private function forgetTheStatusLists(): void
    {
        (new \ReflectionProperty(OrderStatusQuery::class, 'statusIdListsCache'))->setValue(null, []);
    }
}
