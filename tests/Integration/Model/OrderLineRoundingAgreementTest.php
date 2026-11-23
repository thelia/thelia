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
use Propel\Runtime\Propel;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Domain\DataTransfer\Export\Type\OrderExport;
use Thelia\Domain\Order\Sql\OrderAmountSql;
use Thelia\Domain\OrderReturn\Service\RefundAmountCalculator;
use Thelia\Domain\Pricing\MoneyRounding;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Test\IntegrationTestCase;

/**
 * The total of an order, the lines its invoice is booked with, the refund of all its lines and
 * the SQL that totals many orders at once apply one rounding rule: a full refund gives back, to
 * the cent, the goods that were charged.
 * The sums are compared to the cent as the order rounds its total (MoneyRounding), the same
 * on every PHP version.
 */
final class OrderLineRoundingAgreementTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    /**
     * A line is [quantity, price, taxes] and, for a line sold on promotion, [..., promotional
     * price, promotional taxes].
     *
     * @return iterable<string, array{0: string, 1: list<array<mixed>>, 2?: string}>
     */
    public static function orderProvider(): iterable
    {
        foreach ([
            'sum of roundings' => 'sum',
            'rounding of sums' => 'sums',
            'before 2.4' => 'legacy',
            'sum of roundings on a text promotional price' => 'sum-text',
            'rounding of sums on a text promotional price' => 'sums-text',
            'rounding of sums on a floating point line' => 'sums-float',
        ] as $label => $rule) {
            yield $label.', two taxes of half a cent' => [$rule, [[1, '10.00', ['0.125', '0.125']]]];
            yield $label.', price per gram' => [$rule, [[300, '0.005678', ['0.000312']]]];
            yield $label.', a line sold by measure' => [$rule, [['1.25', '14.82', ['0']]]];
            yield $label.', a quantity stored as a single precision float' => [$rule, [[0.69999998, '0.75', ['0']]]];
            yield $label.', a quantity stored as a single precision float, on a dear line' => [$rule, [[0.69999998, '25.75', ['0']]]];
            yield $label.', several lines' => [$rule, [[3, '1.005', ['0.201', '0.0502']], [2, '4.175', ['0.835']], ['0.333', '9.99', ['1.998', '0.0999']]]];
            yield $label.', two lines sold by measure' => [$rule, [['1.25', '14.82', ['0']], ['1.25', '14.82', ['0']]]];
            yield $label.', a promotional price and an order discount' => [$rule, [['1.25', '14.82', ['2.964'], '12.345', ['2.469']], [3, '1.005', ['0.201']]], '5.00'];
        }
    }

    /**
     * @param list<array<mixed>> $lines
     */
    #[DataProvider('orderProvider')]
    public function testTheBookedLinesAddUpToTheGoodsOfTheOrder(string $rule, array $lines, string $discount = '0'): void
    {
        $order = $this->orderUnder($rule, $lines, $discount);
        [$goods, $goodsTax] = $this->goodsOf($order);

        $booked = 0.0;
        $bookedTax = 0.0;
        foreach ($lines as $line) {
            [$quantity, $price, $taxes] = [$line[0], $line[3] ?? $line[1], $line[4] ?? $line[2]];
            [$amount, $tax] = $order->getLineAmounts((float) $quantity, (float) $price, array_map('floatval', $taxes));
            $booked += $amount;
            $bookedTax += $tax;
        }

        self::assertSame(MoneyRounding::toTheCent($goods), MoneyRounding::toTheCent($booked + $bookedTax), 'The booked lines must add up to the goods of the order, tax included.');
        // The tax of an order is the gap between its two rounded totals: the line taxes may add
        // up to half a cent more (18.425 excluding tax, 22.1125 tax included). The journal
        // books the totals of the order and weighs its rates with the lines.
        self::assertSame(MoneyRounding::toTheCent($goods - $goodsTax), MoneyRounding::toTheCent($booked), 'The booked lines must add up to the goods of the order, excluding tax.');
    }

    /**
     * @param list<array<mixed>> $lines
     */
    #[DataProvider('orderProvider')]
    public function testRefundingEveryLineGivesBackTheGoodsOfTheOrder(string $rule, array $lines, string $discount = '0'): void
    {
        $order = $this->orderUnder($rule, $lines, $discount);

        $returned = [];
        foreach ($order->getOrderProducts() as $orderProduct) {
            $returned[] = ['order_product' => $orderProduct, 'quantity' => (float) $orderProduct->getQuantity()];
        }

        // A full refund gives back the goods less the discount, the postage aside.
        $tax = 0;
        $paidForTheGoods = $order->getTotalAmount($tax, false, true);

        self::assertSame(MoneyRounding::toTheCent($paidForTheGoods), MoneyRounding::toTheCent((new RefundAmountCalculator())->linesRefund($order, $returned)));
    }

    /**
     * @param list<array<mixed>> $lines
     */
    #[DataProvider('orderProvider')]
    public function testTheExportStatesTheGoodsOfTheOrder(string $rule, array $lines, string $discount = '0'): void
    {
        $order = $this->orderUnder($rule, $lines, $discount);
        [$goods, $goodsTax] = $this->goodsOf($order);

        // getDataJsonCache() writes its row cache there without creating it.
        (new Filesystem())->mkdir(THELIA_CACHE_DIR.'export');
        $export = new OrderExport();
        $export->setLang(Lang::getDefaultLanguage());
        $export->setRangeDate(null);

        $exported = null;
        foreach ($export as $row) {
            if (($row['order_ref'] ?? null) === $order->getRef()) {
                $exported = $row;
            }
        }

        self::assertNotNull($exported, 'The order must be exported.');
        self::assertSame(MoneyRounding::toTheCent($goods), MoneyRounding::toTheCent((float) $exported['order_total_taxed_price']));
        self::assertSame(MoneyRounding::toTheCent($goods - $goodsTax), MoneyRounding::toTheCent((float) $exported['order_total_price']));
    }

    /**
     * The SQL total a list of orders filters and sums on is the total the order charges:
     * goods, less the discount, plus the postage.
     *
     * @param list<array<mixed>> $lines
     */
    #[DataProvider('orderProvider')]
    public function testTheSqlTotalIsTheTotalTheOrderCharges(string $rule, array $lines, string $discount = '0'): void
    {
        $order = $this->orderUnder($rule, $lines, $discount);
        $charged = MoneyRounding::toTheCent($order->getTotalAmount());

        $sql = 'SELECT '.OrderAmountSql::orderTotal('`order`').' AS total FROM `order` WHERE `order`.id = :order_id';

        $statement = Propel::getConnection()->prepare($sql);
        OrderAmountSql::bindRoundingPivots($statement, $sql);
        $statement->bindValue('order_id', $order->getId(), \PDO::PARAM_INT);
        $statement->execute();
        self::assertSame($charged, MoneyRounding::toTheCent((float) $statement->fetchColumn()));
    }

    /**
     * @return array{float, float} the goods of the order tax included, and their tax
     */
    private function goodsOf(Order $order): array
    {
        $tax = 0;
        $goods = $order->getTotalAmount($tax, false, false);

        return [$goods, (float) $tax];
    }

    /**
     * @param list<array<mixed>> $lines
     */
    private function orderUnder(string $rule, array $lines, string $discount = '0'): Order
    {
        // The fixture takes the postage, not the discount: both are set on the order.
        $order = $this->createFixtureFactory()->order(overrides: ['postage' => '0' === $discount ? '0' : '4.90']);
        $order->setDiscount($discount)->save();

        foreach ($lines as $lineSpec) {
            [$quantity, $price, $taxes] = $lineSpec;
            $promoPrice = $lineSpec[3] ?? null;
            $promoTaxes = $lineSpec[4] ?? $taxes;

            $line = (new OrderProduct())
                ->setOrderId($order->getId())
                ->setProductRef('REF')
                ->setProductSaleElementsRef('PSE')
                ->setProductSaleElementsId(0)
                ->setTitle('Line')
                ->setQuantity((float) $quantity)
                ->setPrice($price)
                ->setPromoPrice($promoPrice ?? $price)
                ->setWasNew(0)
                ->setWasInPromo(null === $promoPrice ? 0 : 1);
            $line->save();

            foreach ($taxes as $index => $tax) {
                (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT')->setAmount($tax)->setPromoAmount($promoTaxes[$index])->save();
            }
        }

        [$mode, $pivot] = explode('-', $rule.'-');
        match ($mode) {
            'sum' => ConfigQuery::write('order_rounding_mode', (string) ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS),
            'sums' => ConfigQuery::write('order_rounding_mode', (string) ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS),
            'legacy' => ConfigQuery::write('last_legacy_rounding_order_id', (string) $order->getId()),
        };
        match ($pivot) {
            'text' => ConfigQuery::write('last_text_promo_price_order_id', (string) $order->getId()),
            'float' => ConfigQuery::write('last_float_line_rounding_order_id', (string) $order->getId()),
            '' => null,
        };
        ConfigQuery::resetCache();

        $order->clearOrderProducts();
        Order::forgetCachedTotals((int) $order->getId());

        return $order;
    }
}
