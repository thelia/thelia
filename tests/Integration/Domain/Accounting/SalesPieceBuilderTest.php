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

namespace Thelia\Tests\Integration\Domain\Accounting;

use Thelia\Domain\Accounting\AccountingChart;
use Thelia\Domain\Accounting\AccountingEntry;
use Thelia\Domain\Accounting\AccountingPiece;
use Thelia\Domain\Accounting\OrderNotExportableException;
use Thelia\Domain\Accounting\SalesPieceBuilder;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPostageTax;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatus;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * One invoice, its entries: the customer debited of what the invoice says, the products
 * credited by tax rate, the tax collected by rate, the shipping on its own line. Each piece
 * balances to the cent, whatever the rounding rule the order was priced with.
 */
final class SalesPieceBuilderTest extends ActionIntegrationTestCase
{
    private SalesPieceBuilder $builder;

    private AccountingChart $chart;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new SalesPieceBuilder();
        $this->chart = AccountingChart::fromValues('VE', 'Ventes', '411000', '708500', '20:706200:445720,5.5:706055:445705,0:706000');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        ConfigQuery::resetCache();
    }

    public function testASingleRateInvoiceDebitsTheCustomerAndCreditsProductTaxAndShipping(): void
    {
        $order = $this->invoicedOrder(postage: 12.0, postageTax: 2.0);
        $this->line($order, 50.0, 2, [10.0]);

        $piece = $this->builder->build($order, $this->chart, $this->defaultCurrency());

        self::assertSame([
            ['411000', 13200, 0],
            ['706200', 0, 10000],
            ['708500', 0, 1000],
            ['445720', 0, 2200],
        ], $this->summary($piece));
        $this->assertBalanced($piece);
    }

    public function testTwoRatesGiveTwoProductLinesAndTwoTaxLines(): void
    {
        $order = $this->invoicedOrder();
        $this->line($order, 100.0, 1, [20.0]);
        $this->line($order, 10.0, 3, [0.55]);

        $piece = $this->builder->build($order, $this->chart, $this->defaultCurrency());

        self::assertSame([
            ['411000', 15165, 0],
            ['706200', 0, 10000],
            ['706055', 0, 3000],
            ['445720', 0, 2000],
            ['445705', 0, 165],
        ], $this->summary($piece));
        $this->assertBalanced($piece);
    }

    public function testADiscountIsSpreadOverTheRatesAndThePieceStillBalances(): void
    {
        $order = $this->invoicedOrder(discount: 15.0);
        $this->line($order, 100.0, 1, [20.0]);
        $this->line($order, 10.0, 3, [0.55]);
        $expectedTax = 0.0;
        $expectedTotal = $order->getTotalAmount($expectedTax);

        $piece = $this->builder->build($order, $this->chart, $this->defaultCurrency());

        $this->assertBalanced($piece);
        self::assertSame((int) round($expectedTotal * 100), $piece->entries[0]->debitCents, 'The customer owes what the invoice says.');
        self::assertSame((int) round($expectedTax * 100), $this->creditsOn($piece, '445720') + $this->creditsOn($piece, '445705'), 'The tax collected is the tax of the invoice.');
        self::assertLessThan(10000, $this->creditsOn($piece, '706200'));
        self::assertLessThan(3000, $this->creditsOn($piece, '706055'));
    }

    public function testAPieceBalancesWhateverTheRoundingRuleOfTheOrder(): void
    {
        foreach ([ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS, ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS] as $mode) {
            ConfigQuery::write('order_rounding_mode', (string) $mode);
            $order = $this->invoicedOrder();
            $this->line($order, 3.333333, 7, [0.666667]);
            $this->line($order, 1.894737, 3, [0.104211]);
            $tax = 0.0;
            $total = $order->getTotalAmount($tax);

            $piece = $this->builder->build($order, $this->chart, $this->defaultCurrency());

            $this->assertBalanced($piece);
            self::assertSame((int) round($total * 100), $piece->entries[0]->debitCents, 'Rounding mode '.$mode);
        }
    }

    public function testAnOrderPricedBeforeTheRoundingRulesStillBalances(): void
    {
        $order = $this->invoicedOrder();
        $this->line($order, 3.333333, 7, [0.666667]);
        ConfigQuery::write('last_legacy_rounding_order_id', (string) $order->getId());
        $tax = 0.0;
        $total = $order->getTotalAmount($tax);

        $piece = $this->builder->build($order, $this->chart, $this->defaultCurrency());

        $this->assertBalanced($piece);
        self::assertSame((int) round($total * 100), $piece->entries[0]->debitCents);
    }

    public function testShippingSplitBetweenTwoRulesIsCreditedByRate(): void
    {
        $order = $this->invoicedOrder(postage: 15.02, postageTax: 2.02);
        $this->line($order, 100.0, 1, [20.0]);
        $this->line($order, 10.0, 1, [0.55]);
        $this->postageShare($order, 9.0, 1.8);
        $this->postageShare($order, 4.0, 0.22);

        $piece = $this->builder->build($order, $this->chart, $this->defaultCurrency());

        $this->assertBalanced($piece);
        self::assertSame(1300, $this->creditsOn($piece, '708500'));
        self::assertSame(2000 + 180, $this->creditsOn($piece, '445720'));
        self::assertSame(55 + 22, $this->creditsOn($piece, '445705'));
    }

    public function testARateRoundedOffTheChartIsFiledUnderTheChartRate(): void
    {
        // 0.83 of tax on 4.17 makes 19.90%: the frozen amounts were rounded, the rate is 20.
        $order = $this->invoicedOrder();
        $this->line($order, 4.17, 1, [0.83]);

        $piece = $this->builder->build($order, $this->chart, $this->defaultCurrency());

        self::assertSame(417, $this->creditsOn($piece, '706200'));
        self::assertSame(83, $this->creditsOn($piece, '445720'));
    }

    public function testACheapLineIsFiledUnderTheRateItsTaxWasRoundedFrom(): void
    {
        // 0.20 of tax on 0.99 makes 20.20%, 0.06 on 1.00 makes 6%: both a cent off the
        // 20% and 5.5% the chart knows, as rounding to the cent leaves them.
        $order = $this->invoicedOrder();
        $this->line($order, 0.99, 3, [0.20]);
        $this->line($order, 1.00, 2, [0.06]);

        $piece = $this->builder->build($order, $this->chart, $this->defaultCurrency());

        $this->assertBalanced($piece);
        self::assertSame(297, $this->creditsOn($piece, '706200'));
        self::assertSame(200, $this->creditsOn($piece, '706055'));
    }

    public function testRatesRoundedDifferentlyAreBookedTogether(): void
    {
        $order = $this->invoicedOrder(postage: 11.93, postageTax: 1.98);
        $this->line($order, 4.17, 1, [0.83]);
        $this->line($order, 100.0, 1, [20.0]);
        $this->postageShare($order, 9.95, 1.98);

        $piece = $this->builder->build($order, $this->chart, $this->defaultCurrency());

        $this->assertBalanced($piece);
        self::assertSame([['411000', 13693, 0], ['706200', 0, 10417], ['708500', 0, 995], ['445720', 0, 2281]], $this->summary($piece), 'One line per account: 19.90%, 20% and the shipping at 19.90% are all the 20% of the chart.');
    }

    public function testALineTaxedTwiceIsFiledUnderTheSumOfItsRates(): void
    {
        $order = $this->invoicedOrder();
        $this->line($order, 100.0, 1, [20.0, 2.0]);
        $chart = AccountingChart::fromValues('VE', 'Ventes', '411000', '708500', '22:706220:445722');

        $piece = $this->builder->build($order, $chart, $this->defaultCurrency());

        self::assertSame(10000, $this->creditsOn($piece, '706220'));
        self::assertSame(2200, $this->creditsOn($piece, '445722'));
    }

    public function testUntaxedShippingNeedsNoRateOfItsOwn(): void
    {
        $order = $this->invoicedOrder(postage: 10.0, postageTax: 0.0);
        $this->line($order, 100.0, 1, [20.0]);
        $chart = AccountingChart::fromValues('VE', 'Ventes', '411000', '708500', '20:706200:445720');

        $piece = $this->builder->build($order, $chart, $this->defaultCurrency());

        $this->assertBalanced($piece);
        self::assertSame(1000, $this->creditsOn($piece, '708500'));
    }

    public function testAPromotedLineIsBookedAtItsPromotedPrice(): void
    {
        $order = $this->invoicedOrder();
        $this->line($order, 100.0, 1, [20.0], promoPrice: 80.0, promoTax: 16.0);

        $piece = $this->builder->build($order, $this->chart, $this->defaultCurrency());

        self::assertSame(8000, $this->creditsOn($piece, '706200'));
        self::assertSame(1600, $this->creditsOn($piece, '445720'));
    }

    public function testAFreeLineChangesNothing(): void
    {
        $order = $this->invoicedOrder();
        $this->line($order, 100.0, 1, [20.0]);
        $this->line($order, 0.0, 1, [0.0]);

        $piece = $this->builder->build($order, $this->chart, $this->defaultCurrency());

        self::assertSame([['411000', 12000, 0], ['706200', 0, 10000], ['445720', 0, 2000]], $this->summary($piece));
    }

    public function testAnOrderWithoutExchangeRateIsLeftOut(): void
    {
        $order = $this->invoicedOrder();
        $dollar = $this->factory->currency(['code' => 'USD', 'symbol' => '$', 'rate' => 1.25]);
        $order->setCurrencyId((int) $dollar->getId())->setCurrencyRate(0.0)->save();
        $this->line($order, 100.0, 1, [20.0]);

        $this->expectException(OrderNotExportableException::class);

        $this->builder->build($order, $this->chart, $this->defaultCurrency());
    }

    public function testARateWithoutAccountKeepsTheOrderOutWithTheReason(): void
    {
        $order = $this->invoicedOrder();
        $this->line($order, 100.0, 1, [10.0]);

        $this->expectException(OrderNotExportableException::class);
        $this->expectExceptionMessage('10.00%');

        $this->builder->build($order, $this->chart, $this->defaultCurrency());
    }

    public function testAnOrderInAnotherCurrencyIsBookedInTheShopCurrencyWithItsOwnAmount(): void
    {
        $order = $this->invoicedOrder();
        $dollar = $this->factory->currency(['code' => 'USD', 'symbol' => '$', 'rate' => 1.25]);
        $order->setCurrencyId((int) $dollar->getId())->setCurrencyRate(1.25)->save();
        $this->line($order, 100.0, 1, [20.0]);

        $piece = $this->builder->build($order, $this->chart, $this->defaultCurrency());

        $this->assertBalanced($piece);
        self::assertSame(9600, $piece->entries[0]->debitCents, '120 in the order currency, at 1.25 to the shop currency.');
        self::assertSame(12000, $piece->entries[0]->foreignCents);
        self::assertSame('USD', $piece->foreignCurrencyCode);
    }

    private function invoicedOrder(float $postage = 0.0, float $postageTax = 0.0, float $discount = 0.0): Order
    {
        $order = $this->factory->order(null, ['postage' => $postage, 'postageTax' => $postageTax, 'statusCode' => OrderStatus::CODE_PAID]);
        $order
            ->setInvoiceRef('2026-'.str_pad((string) $order->getId(), 6, '0', \STR_PAD_LEFT))
            ->setInvoiceDate(new \DateTime('2026-02-10 10:00:00'))
            ->setDiscount((string) $discount)
            ->save();

        $order->setCurrencyId((int) $this->defaultCurrency()->getId())->save();

        return $order;
    }

    /**
     * @param list<float> $unitTaxes
     */
    private function line(Order $order, float $unitPrice, int $quantity, array $unitTaxes, ?float $promoPrice = null, ?float $promoTax = null): void
    {
        $line = (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef('REF-'.uniqid())
            ->setProductSaleElementsRef('PSE-'.uniqid())
            ->setProductSaleElementsId(0)
            ->setTitle('Line')
            ->setQuantity($quantity)
            ->setPrice((string) $unitPrice)
            ->setPromoPrice((string) ($promoPrice ?? $unitPrice))
            ->setWasNew(0)
            ->setWasInPromo(null !== $promoPrice ? 1 : 0);
        $line->save();

        foreach ($unitTaxes as $unitTax) {
            (new OrderProductTax())
                ->setOrderProductId($line->getId())
                ->setTitle('VAT')
                ->setAmount((string) $unitTax)
                ->setPromoAmount((string) ($promoTax ?? $unitTax))
                ->save();
        }
    }

    private function postageShare(Order $order, float $untaxed, float $tax): void
    {
        (new OrderPostageTax())
            ->setOrderId($order->getId())
            ->setTitle('Shipping share')
            ->setUntaxedAmount((string) $untaxed)
            ->setAmount((string) $tax)
            ->save();
    }

    private function defaultCurrency(): Currency
    {
        return CurrencyQuery::create()->findOneByByDefault(1);
    }

    private function assertBalanced(AccountingPiece $piece): void
    {
        $debit = array_sum(array_map(static fn (AccountingEntry $entry): int => $entry->debitCents, $piece->entries));
        $credit = array_sum(array_map(static fn (AccountingEntry $entry): int => $entry->creditCents, $piece->entries));

        self::assertSame($debit, $credit, 'A piece balances to the cent.');
        self::assertGreaterThan(0, $debit);
    }

    private function creditsOn(AccountingPiece $piece, string $account): int
    {
        return array_sum(array_map(static fn (AccountingEntry $entry): int => $account === $entry->account ? $entry->creditCents : 0, $piece->entries));
    }

    /**
     * @return list<array{string, int, int}>
     */
    private function summary(AccountingPiece $piece): array
    {
        return array_map(static fn (AccountingEntry $entry): array => [$entry->account, $entry->debitCents, $entry->creditCents], $piece->entries);
    }
}
