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

namespace Thelia\Tests\Integration\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Api\Service\DataAccess\LoopDataAccessService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTax;
use Thelia\Test\IntegrationTestCase;

/**
 * What a line of an order is worth is built in one place, the model, whichever rounding the
 * order was invoiced with: the order product loop, the order API and the order total read it
 * from there. 3 units of 33.333 taxed 6.6666 give a different line under each of the three.
 */
final class OrderProductLineTotalsTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: array{untaxed: float, taxed: float, tax: float}}>
     */
    public static function modeProvider(): array
    {
        return [
            'sum of roundings' => ['sum', ['untaxed' => 99.99, 'taxed' => 120.0, 'tax' => 20.01]],
            'rounding of sums' => ['rounding', ['untaxed' => 100.0, 'taxed' => 120.0, 'tax' => 20.0]],
            'order before the rounding modes' => ['legacy', ['untaxed' => 99.999, 'taxed' => 120.0, 'tax' => 20.0]],
        ];
    }

    /**
     * @param array{untaxed: float, taxed: float, tax: float} $expected
     */
    #[DataProvider('modeProvider')]
    public function testALineIsTotalledTheWayItsOrderWasInvoiced(string $mode, array $expected): void
    {
        $line = $this->lineInvoicedWith($mode);

        $totals = $line->getLineTotals(false);

        self::assertEqualsWithDelta($expected['untaxed'], $totals['untaxed'], 1e-9);
        self::assertEqualsWithDelta($expected['taxed'], $totals['taxed'], 1e-9);
        self::assertEqualsWithDelta($expected['tax'], $totals['tax'], 1e-9);
        self::assertSame($totals, $line->getChargedLineTotals(), 'A line off promotion is charged its price.');
    }

    /**
     * @param array{untaxed: float, taxed: float, tax: float} $expected
     */
    #[DataProvider('modeProvider')]
    public function testTheOrderProductLoopPublishesTheTotalsOfTheModel(string $mode, array $expected): void
    {
        $line = $this->lineInvoicedWith($mode);

        $rows = $this->getService(LoopDataAccessService::class)
            ->theliaLoop('lines', 'order_product', ['order' => $line->getOrderId()]);

        self::assertCount(1, $rows);
        self::assertEqualsWithDelta($expected['untaxed'], (float) $rows[0]['TOTAL_PRICE'], 1e-9);
        self::assertEqualsWithDelta($expected['taxed'], (float) $rows[0]['TOTAL_TAXED_PRICE'], 1e-9);
        self::assertEqualsWithDelta($expected['tax'], (float) $rows[0]['REAL_TOTAL_PRICE_TAX'], 1e-9);
    }

    public function testAQuantityOfOneShipmentTotalsOnlyThatQuantity(): void
    {
        $line = $this->lineInvoicedWith('rounding');

        $totals = $line->getLineTotals(false, 1.0);

        // 33.333 and 39.9996 for one unit, cut to the cent as the whole line is.
        self::assertEqualsWithDelta(33.33, $totals['untaxed'], 1e-9);
        self::assertEqualsWithDelta(40.0, $totals['taxed'], 1e-9);
    }

    #[DataProvider('modeProvider')]
    public function testAUnitAmountKeepsTheCentOnlyWhereTheTotalDoes(string $mode): void
    {
        $line = $this->lineInvoicedWith($mode);

        $expected = 'rounding' === $mode ? 33.333 : 33.33;

        self::assertEqualsWithDelta($expected, $line->roundUnitAmount(33.333), 1e-9);
    }

    private function lineInvoicedWith(string $mode): OrderProduct
    {
        $factory = $this->createFixtureFactory();
        $connection = $this->getPropelConnection();

        ConfigQuery::write('order_rounding_mode', 'rounding' === $mode ? ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS : ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS);

        $order = $factory->order($factory->customer($factory->customerTitle()));
        $line = $factory->orderProduct($order, ['productRef' => 'LINE', 'price' => '33.333000', 'promoPrice' => '10.000000', 'quantity' => 3.0]);

        (new OrderProductTax())
            ->setOrderProductId($line->getId())
            ->setTitle('VAT')
            ->setAmount('6.666600')
            ->setPromoAmount('2.000000')
            ->save($connection);

        if ('legacy' === $mode) {
            $this->freezeAsLegacy($order);
        }

        // A fresh read: the line holds no tax row of the instance the factory saved.
        return OrderProductQuery::create()->findPk($line->getId());
    }

    private function freezeAsLegacy(Order $order): void
    {
        ConfigQuery::write('last_legacy_rounding_order_id', (string) $order->getId());
    }
}
