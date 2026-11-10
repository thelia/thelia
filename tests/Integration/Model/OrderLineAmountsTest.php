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
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Test\IntegrationTestCase;

/**
 * The rounding of a line the invoice of an order is booked with is the one its total is
 * computed with.
 */
final class OrderLineAmountsTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{?int, bool}>
     */
    public static function roundingProvider(): iterable
    {
        yield 'sum of roundings' => [ConfigQuery::ROUNDING_MODE_SUM_OF_ROUNDINGS, false];
        yield 'rounding of sums' => [ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS, false];
        yield 'before both rules' => [null, true];
    }

    #[DataProvider('roundingProvider')]
    public function testALineIsRoundedAsTheTotalOfTheOrderIs(?int $mode, bool $legacy): void
    {
        $order = $this->createFixtureFactory()->order();
        $line = (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef('REF')
            ->setProductSaleElementsRef('PSE')
            ->setProductSaleElementsId(0)
            ->setTitle('Bulk rice')
            ->setQuantity(300)
            ->setPrice('0.005678')
            ->setPromoPrice('0.005678')
            ->setWasNew(0)
            ->setWasInPromo(0);
        $line->save();
        (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT')->setAmount('0.000312')->setPromoAmount('0.000312')->save();

        if (null !== $mode) {
            ConfigQuery::write('order_rounding_mode', (string) $mode);
        }

        if ($legacy) {
            ConfigQuery::write('last_legacy_rounding_order_id', (string) $order->getId());
        }

        ConfigQuery::resetCache();

        $tax = 0;
        $total = $order->getTotalAmount($tax, false, false);
        [$amount, $lineTax] = $order->getLineAmounts(300, 0.005678, [0.000312]);

        self::assertEqualsWithDelta($total, $amount + $lineTax, 0.0001);
        self::assertEqualsWithDelta($tax, $lineTax, 0.0001);
    }
}
