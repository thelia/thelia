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
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Domain\DataTransfer\Export\Type\OrderExport;
use Thelia\Domain\OrderReturn\Service\RefundAmountCalculator;
use Thelia\Domain\Pricing\MoneyRounding;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Test\IntegrationTestCase;

/**
 * The total of an order, the lines its invoice is booked with and the refund of all its lines
 * apply one rounding rule: a full refund gives back, to the cent, the goods that were charged.
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
     * @return iterable<string, array{string, list<array{float|string, string, list<string>}>}>
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
        }
    }

    /**
     * @param list<array{float|string, string, list<string>}> $lines
     */
    #[DataProvider('orderProvider')]
    public function testTheBookedLinesAddUpToTheGoodsOfTheOrder(string $rule, array $lines): void
    {
        $order = $this->orderUnder($rule, $lines);
        [$goods, $goodsTax] = $this->goodsOf($order);

        $booked = 0.0;
        $bookedTax = 0.0;
        foreach ($lines as [$quantity, $price, $taxes]) {
            [$amount, $tax] = $order->getLineAmounts((float) $quantity, (float) $price, array_map('floatval', $taxes));
            $booked += $amount;
            $bookedTax += $tax;
        }

        self::assertSame(MoneyRounding::toTheCent($goods), MoneyRounding::toTheCent($booked + $bookedTax), 'The booked lines must add up to the goods of the order, tax included.');
        self::assertSame(MoneyRounding::toTheCent($goodsTax), MoneyRounding::toTheCent($bookedTax), 'The booked tax must be the tax of the goods of the order.');
    }

    /**
     * @param list<array{float|string, string, list<string>}> $lines
     */
    #[DataProvider('orderProvider')]
    public function testRefundingEveryLineGivesBackTheGoodsOfTheOrder(string $rule, array $lines): void
    {
        $order = $this->orderUnder($rule, $lines);
        [$goods] = $this->goodsOf($order);

        $returned = [];
        foreach ($order->getOrderProducts() as $orderProduct) {
            $returned[] = ['order_product' => $orderProduct, 'quantity' => (float) $orderProduct->getQuantity()];
        }

        self::assertSame(MoneyRounding::toTheCent($goods), MoneyRounding::toTheCent((new RefundAmountCalculator())->linesRefund($order, $returned)));
    }

    /**
     * @param list<array{float|string, string, list<string>}> $lines
     */
    #[DataProvider('orderProvider')]
    public function testTheExportStatesTheGoodsOfTheOrder(string $rule, array $lines): void
    {
        $order = $this->orderUnder($rule, $lines);
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
     * @return array{float, float} the goods of the order tax included, and their tax
     */
    private function goodsOf(Order $order): array
    {
        $tax = 0;
        $goods = $order->getTotalAmount($tax, false, false);

        return [$goods, (float) $tax];
    }

    /**
     * @param list<array{float|string, string, list<string>}> $lines
     */
    private function orderUnder(string $rule, array $lines): Order
    {
        $order = $this->createFixtureFactory()->order(overrides: ['discount' => 0]);

        foreach ($lines as [$quantity, $price, $taxes]) {
            $line = (new OrderProduct())
                ->setOrderId($order->getId())
                ->setProductRef('REF')
                ->setProductSaleElementsRef('PSE')
                ->setProductSaleElementsId(0)
                ->setTitle('Line')
                ->setQuantity((float) $quantity)
                ->setPrice($price)
                ->setPromoPrice($price)
                ->setWasNew(0)
                ->setWasInPromo(0);
            $line->save();

            foreach ($taxes as $tax) {
                (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT')->setAmount($tax)->setPromoAmount($tax)->save();
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
