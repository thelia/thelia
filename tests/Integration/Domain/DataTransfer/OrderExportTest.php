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

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Domain\DataTransfer\Export\Type\OrderExport;
use Thelia\Domain\DataTransfer\Export\Type\OrderLineExport;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\Order;
use Thelia\Model\OrderCoupon;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The order export is the only one bounded by dates. `php Thelia export` used
 * to be unable to run it at all, having no way to supply a range.
 */
final class OrderExportTest extends IntegrationTestCase
{
    /** One gram of a product priced 5.678 € the kilogram, taxed 0.312 € the kilogram. */
    private const PRICE_PER_GRAM = '0.005678';

    private const TAX_PER_GRAM = '0.000312';

    private const GRAMS = 300.0;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();

        // getDataJsonCache() writes its row cache there without creating it;
        // only ExportHandler::processExport() does, and this test does not go
        // through the handler.
        (new Filesystem())->mkdir(THELIA_CACHE_DIR.'export');
    }

    protected function tearDown(): void
    {
        // ConfigQuery caches in a static array the transaction rollback cannot
        // reach, so a mode written here would answer the next test.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testItExportsEveryOrderWhenNoRangeIsGiven(): void
    {
        $old = $this->orderCreatedAt('-3 years');
        $recent = $this->orderCreatedAt('-1 day');

        $refs = $this->exportedRefs(null);

        self::assertContains($old->getRef(), $refs);
        self::assertContains($recent->getRef(), $refs);
    }

    public function testItKeepsOnlyTheOrdersInsideTheRange(): void
    {
        $old = $this->orderCreatedAt('-3 years');
        $recent = $this->orderCreatedAt('-1 day');

        $refs = $this->exportedRefs([
            'start' => new \DateTime('-1 month'),
            'end' => new \DateTime(),
        ]);

        self::assertContains($recent->getRef(), $refs);
        self::assertNotContains($old->getRef(), $refs);
    }

    public function testASingleBoundIsEnoughToFilter(): void
    {
        $old = $this->orderCreatedAt('-3 years');
        $recent = $this->orderCreatedAt('-1 day');

        $refs = $this->exportedRefs(['start' => new \DateTime('-1 month'), 'end' => null]);

        self::assertContains($recent->getRef(), $refs);
        self::assertNotContains($old->getRef(), $refs);
    }

    /**
     * `postage_tax_rule_title` is a VARCHAR. Wrapping it in ROUND() made
     * MySQL coerce the label to a number, so every exported order carried
     * 0.00 instead of the tax rule name.
     */
    public function testItExportsTheShippingTaxRuleTitleAsText(): void
    {
        $order = $this->factory->order();
        $order->setRef('EXP-'.uniqid());
        $order->setPostageTaxRuleTitle('TVA 20%');
        $order->save($this->getPropelConnection());

        $row = $this->exportedRow($order->getRef());

        self::assertSame('TVA 20%', $row['order_postage_tax_rule_title']);
    }

    /**
     * Both country columns used to be aliased to `invoice_country`, and the
     * second one silently overwrote the first: the delivery country never
     * reached the file.
     */
    public function testTheDeliveryAndInvoiceCountriesGetTheirOwnColumn(): void
    {
        $order = $this->factory->order();
        $order->setRef('EXP-'.uniqid());
        $order->save($this->getPropelConnection());

        $row = $this->exportedRow($order->getRef());
        $aliased = (new OrderExport())->applyOrderAndAliases($row);

        self::assertArrayHasKey('delivery_country', $aliased);
        self::assertSame($row['delivery_country_i18n_title'], $aliased['delivery_country']);
        self::assertSame($row['invoice_country_i18n_title'], $aliased['invoice_country']);
    }

    /**
     * A shop selling by weight stores a price per gram, and the mode the shop
     * runs decides where the cent appears. The default rounds the unit price
     * first: 0.005678 € the gram becomes 0.01 €, and the 300 g line is invoiced
     * 3.00 €. An export stating 1.70 € for it states an amount nobody charged.
     */
    public function testTheDefaultModeExportsTheAmountTheOrderWasInvoicedWith(): void
    {
        $order = $this->bulkOrder();

        self::assertEqualsWithDelta(3.0, $this->invoicedTotal($order), 0.0001);
        $this->assertExportedTotalsMatchTheInvoice($order);
    }

    /**
     * Rounding of sums multiplies at the stored precision and rounds the line
     * total: 300 x 0.005678 = 1.7034 before tax, 1.797 with it.
     */
    public function testRoundingOfSumsExportsTheAmountTheOrderWasInvoicedWith(): void
    {
        $this->optIn();

        $order = $this->bulkOrder();

        self::assertEqualsWithDelta(1.80, $this->invoicedTotal($order), 0.0001);
        $this->assertExportedTotalsMatchTheInvoice($order);
    }

    /**
     * The pivot a shop writes when it opts in: the orders it had already
     * invoiced keep the rule they were charged with, and so does their line in
     * the export.
     */
    public function testAnOrderFrozenByThePivotIsExportedWithTheHistoricalRule(): void
    {
        $order = $this->bulkOrder();

        ConfigQuery::write('last_sum_of_roundings_order_id', (string) $order->getId());
        $this->optIn();

        self::assertEqualsWithDelta(3.0, $this->invoicedTotal($order), 0.0001);
        $this->assertExportedTotalsMatchTheInvoice($order);
    }

    /**
     * Orders placed before Thelia 2.4 were totalled without any rounding at all,
     * and the amount their invoice states cannot be restated afterwards.
     */
    public function testAnOrderFrozenByThe24UpgradeIsExportedWithoutRounding(): void
    {
        $this->optIn();

        $order = $this->bulkOrder();

        ConfigQuery::write('last_legacy_rounding_order_id', (string) $order->getId());

        self::assertEqualsWithDelta(1.797, $this->invoicedTotal($order), 0.0001);
        $this->assertExportedTotalsMatchTheInvoice($order);
    }

    /**
     * `order_product`.`promo_price` was a text before 3.3.0, and an order placed then was totalled with the promotional
     * price read as a text: 63.325 is 63.32 and not 63.33. The pivot written by the conversion keeps that amount in the
     * file, as it is on the invoice.
     */
    public function testAnOrderPlacedBeforeThePromotionalPriceBecameADecimalIsExportedWithItsInvoicedTotal(): void
    {
        $order = $this->promotionalOrder();

        ConfigQuery::write('last_text_promo_price_order_id', (string) $order->getId());

        self::assertEqualsWithDelta(75.99, $this->invoicedTotal($order), 0.0001);
        $this->assertExportedTotalsMatchTheInvoice($order);
        self::assertSame(75.99, (float) $this->exportedRow($order->getRef())['order_total_taxed_price']);
    }

    public function testAnOrderPlacedAfterThePivotIsExportedWithTheDecimal(): void
    {
        $frozen = $this->promotionalOrder();
        ConfigQuery::write('last_text_promo_price_order_id', (string) $frozen->getId());

        $order = $this->promotionalOrder();

        self::assertEqualsWithDelta(76.0, $this->invoicedTotal($order), 0.0001);
        $this->assertExportedTotalsMatchTheInvoice($order);
        self::assertSame(76.0, (float) $this->exportedRow($order->getRef())['order_total_taxed_price']);
    }

    /**
     * ROUND() of a text is a floating point number, and so is its product by the quantity: 3 x (63.32 + 12.67) gives
     * 227.96999999999997. The total of the order is brought back to a decimal after the sum, to state 227.97.
     */
    public function testTheTotalOfAFrozenOrderIsStatedWithoutFloatingPointNoise(): void
    {
        $order = $this->promotionalOrder(3.0);
        ConfigQuery::write('last_text_promo_price_order_id', (string) $order->getId());

        self::assertSame(227.97, (float) $this->exportedRow($order->getRef())['order_total_taxed_price']);
    }

    /** The cast is made on the sum of the order: a cast on each line leaves clean doubles whose SUM is noisy (75.99 + 151.98 gives 227.96999999999997). */
    public function testTheTotalOfAFrozenOrderOfTwoLinesIsStatedWithoutFloatingPointNoise(): void
    {
        $order = $this->promotionalOrder(1.0, 2.0);
        ConfigQuery::write('last_text_promo_price_order_id', (string) $order->getId());

        $row = $this->exportedRow($order->getRef());

        self::assertSame(227.97, (float) $row['order_total_taxed_price']);
    }

    /** A promotional line whose text was empty is NULL once converted: it counts for 0, plus its tax, as before. */
    public function testAFrozenPromotionalLineWithoutPriceIsCountedForZeroPlusItsTax(): void
    {
        $order = $this->promotionalOrder();
        $this->getPropelConnection()->exec('UPDATE order_product SET promo_price = NULL WHERE order_id = '.(int) $order->getId());
        ConfigQuery::write('last_text_promo_price_order_id', (string) $order->getId());

        $row = $this->exportedRow($order->getRef());

        self::assertEqualsWithDelta(12.67, (float) $row['order_total_taxed_price'], 0.0001);
        self::assertEqualsWithDelta(0.0, (float) $row['order_total_price'], 0.0001);
        self::assertEqualsWithDelta(12.67, $this->invoicedTotal($order), 0.0001);
    }

    public function testAFrozenOrderIsExportedWithItsInvoicedTotalUnderRoundingOfSums(): void
    {
        $this->optIn();

        $order = $this->promotionalOrder();
        ConfigQuery::write('last_text_promo_price_order_id', (string) $order->getId());

        $this->assertExportedTotalsMatchTheInvoice($order);
    }

    public function testAFrozenOrderIsExportedWithItsInvoicedTotalWhenTheUpgradeTo24FrozeIt(): void
    {
        $order = $this->promotionalOrder();
        ConfigQuery::write('last_legacy_rounding_order_id', (string) $order->getId());
        ConfigQuery::write('last_text_promo_price_order_id', (string) $order->getId());

        $this->assertExportedTotalsMatchTheInvoice($order);
    }

    public function testTheOrderLineExportStatesTheUnitPriceOfAnOrderPlacedBeforeThePivot(): void
    {
        $frozen = $this->promotionalOrder();
        ConfigQuery::write('last_text_promo_price_order_id', (string) $frozen->getId());
        $order = $this->promotionalOrder();

        self::assertSame(63.32, (float) $this->exportedLineRow($frozen->getRef())['order_product_unit_price']);
        self::assertSame(63.33, (float) $this->exportedLineRow($order->getRef())['order_product_unit_price']);
    }

    /** A line that is not on promotion was read as a text too: 63.325 gave 63.32. */
    public function testTheOrderLineExportFreezesALineThatIsNotOnPromotionToo(): void
    {
        $order = $this->factory->order();
        $order->setRef('EXP-'.uniqid());
        $order->save($this->getPropelConnection());
        $this->factory->orderProduct($order, ['price' => '63.325000']);

        self::assertSame(63.33, (float) $this->exportedLineRow($order->getRef())['order_product_unit_price']);

        ConfigQuery::write('last_text_promo_price_order_id', (string) $order->getId());

        self::assertSame(63.32, (float) $this->exportedLineRow($order->getRef())['order_product_unit_price']);
    }

    /**
     * A line bearing two taxes is one line on the invoice. The export joined
     * order_product_tax at the top level, so the line reached the SUM() once
     * per tax row and the order came out doubled.
     */
    public function testALineTaxedTwiceIsTotalledOnce(): void
    {
        $order = $this->twiceTaxedOrder();

        self::assertEqualsWithDelta(115.0, $this->invoicedTotal($order), 0.0001);
        $this->assertExportedTotalsMatchTheInvoice($order);
    }

    /**
     * Same multiplication through the coupons: an order carrying two of them
     * was totalled twice, and one carrying three of them three times.
     */
    public function testAnOrderCarryingSeveralCouponsIsTotalledOnce(): void
    {
        $order = $this->twiceTaxedOrder();
        $this->addCoupon($order, 'SPRING');
        $this->addCoupon($order, 'SUMMER');

        $this->assertExportedTotalsMatchTheInvoice($order);
    }

    /**
     * The column holds the tax label, as it has since Thelia 2. Picking one
     * row out of a group left the others out of the file, and which one
     * survived was up to the database.
     */
    public function testEveryTaxLabelOfTheOrderReachesTheTaxColumn(): void
    {
        $order = $this->twiceTaxedOrder();

        self::assertSame('Eco tax, VAT', $this->exportedRow($order->getRef())['order_product_tax_title']);
    }

    /** The column is aliased `coupons`, and an order can carry several. */
    public function testEveryCouponCodeOfTheOrderReachesTheCouponColumn(): void
    {
        $order = $this->twiceTaxedOrder();
        $this->addCoupon($order, 'SPRING');
        $this->addCoupon($order, 'SUMMER');

        self::assertSame('SPRING, SUMMER', $this->exportedRow($order->getRef())['order_coupon_code']);
    }

    /** The taxed total of the goods, the figure the invoice states for the lines. */
    private function invoicedTotal(Order $order): float
    {
        $tax = 0.0;

        return $order->getTotalAmount($tax, false, false);
    }

    private function optIn(): void
    {
        ConfigQuery::write('order_rounding_mode', (string) ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS);
    }

    /**
     * An order of one line of goods sold by weight, the case where the two
     * rounding modes land more than a cent apart.
     */
    private function bulkOrder(): Order
    {
        $order = $this->factory->order();
        $order->setRef('EXP-'.uniqid());
        $order->save($this->getPropelConnection());

        $orderProduct = new OrderProduct();
        $orderProduct
            ->setOrderId($order->getId())
            ->setProductRef('bulk-ref')
            ->setProductSaleElementsRef('bulk-pse-ref')
            ->setTitle('Bulk rice')
            ->setQuantity(self::GRAMS)
            ->setPrice(self::PRICE_PER_GRAM)
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save($this->getPropelConnection());

        (new OrderProductTax())
            ->setOrderProductId($orderProduct->getId())
            ->setTitle('VAT')
            ->setDescription('')
            ->setAmount(self::TAX_PER_GRAM)
            ->save($this->getPropelConnection());

        return $order;
    }

    /**
     * One promotional line at 63.325 €, bearing a 12.665 € tax per unit: 63.32 + 12.67 when the promotional price is read
     * as a text, 63.33 + 12.67 when it is read as a decimal.
     */
    private function promotionalOrder(float ...$quantities): Order
    {
        $order = $this->factory->order();
        $order->setRef('EXP-'.uniqid());
        $order->save($this->getPropelConnection());

        foreach ($quantities ?: [1.0] as $quantity) {
            $line = $this->factory->orderProduct($order, ['price' => '66.658333', 'quantity' => $quantity]);
            $line->setWasInPromo(1)->setPromoPrice('63.325000')->save($this->getPropelConnection());

            (new OrderProductTax())
                ->setOrderProductId($line->getId())
                ->setTitle('VAT')
                ->setDescription('')
                ->setAmount('13.331667')
                ->setPromoAmount('12.665000')
                ->save($this->getPropelConnection());
        }

        return $order;
    }

    /** One line of 100.00 € bearing a 10.00 € VAT and a 5.00 € eco tax: 115.00 € invoiced. */
    private function twiceTaxedOrder(): Order
    {
        $order = $this->factory->order();
        $order->setRef('EXP-'.uniqid());
        $order->save($this->getPropelConnection());

        $orderProduct = new OrderProduct();
        $orderProduct
            ->setOrderId($order->getId())
            ->setProductRef('taxed-ref')
            ->setProductSaleElementsRef('taxed-pse-ref')
            ->setTitle('Twice taxed goods')
            ->setQuantity(1.0)
            ->setPrice('100.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save($this->getPropelConnection());

        foreach (['VAT' => '10.000000', 'Eco tax' => '5.000000'] as $title => $amount) {
            (new OrderProductTax())
                ->setOrderProductId($orderProduct->getId())
                ->setTitle($title)
                ->setDescription('')
                ->setAmount($amount)
                ->save($this->getPropelConnection());
        }

        return $order;
    }

    private function addCoupon(Order $order, string $code): void
    {
        (new OrderCoupon())
            ->setOrderId($order->getId())
            ->setCode($code)
            ->setType('thelia.coupon.type.remove_x_amount')
            ->setAmount('0.000000')
            ->setTitle($code)
            ->setShortDescription('')
            ->setDescription('')
            ->setExpirationDate(new \DateTime('+1 year'))
            ->setIsCumulative(true)
            ->setIsRemovingPostage(false)
            ->setIsAvailableOnSpecialOffers(false)
            ->setSerializedConditions('')
            ->setPerCustomerUsageCount(false)
            ->save($this->getPropelConnection());
    }

    /**
     * The export carries the goods alone: postage and discount get their own
     * columns, so the order is read the same way.
     */
    private function assertExportedTotalsMatchTheInvoice(Order $order): void
    {
        $tax = 0.0;
        $invoicedTaxedTotal = $order->getTotalAmount($tax, false, false);

        $row = $this->exportedRow($order->getRef());

        self::assertEqualsWithDelta(
            $invoicedTaxedTotal,
            (float) $row['order_total_taxed_price'],
            0.0001,
            'The exported taxed total has to be the amount the order was invoiced.',
        );
        self::assertEqualsWithDelta($invoicedTaxedTotal - $tax, (float) $row['order_total_price'], 0.0001);
        self::assertEqualsWithDelta($tax, (float) $row['order_total_tax'], 0.0001);
    }

    private function orderCreatedAt(string $modifier): Order
    {
        $order = $this->factory->order();
        $order->setRef('EXP-'.uniqid());
        $order->setCreatedAt(new \DateTime($modifier));
        $order->save($this->getPropelConnection());

        return $order;
    }

    /**
     * @param array<string, \DateTime|null>|null $rangeDate
     *
     * @return array<int, string>
     */
    private function exportedRefs(?array $rangeDate): array
    {
        $export = new OrderExport();
        $export->setLang(Lang::getDefaultLanguage());
        $export->setRangeDate($rangeDate);

        $refs = [];

        foreach ($export as $row) {
            if (isset($row['order_ref'])) {
                $refs[] = $row['order_ref'];
            }
        }

        return $refs;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportedLineRow(string $ref): array
    {
        $export = new OrderLineExport();
        $export->setLang(Lang::getDefaultLanguage());
        $export->setRangeDate(null);

        foreach ($export as $row) {
            if (($row['order_ref'] ?? null) === $ref) {
                return $row;
            }
        }

        self::fail("Order $ref is missing from the order line export.");
    }

    /**
     * The export yields the raw column names, not the output aliases.
     *
     * @return array<string, mixed>
     */
    private function exportedRow(string $ref): array
    {
        $export = new OrderExport();
        $export->setLang(Lang::getDefaultLanguage());
        $export->setRangeDate(null);

        foreach ($export as $row) {
            if (($row['order_ref'] ?? null) === $ref) {
                return $row;
            }
        }

        self::fail("Order $ref is missing from the export.");
    }
}
