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

use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProductTax;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * `order_product`.`promo_price` was a VARCHAR(45) on a shop created by Thelia 2 and became a DECIMAL(16,6) in 3.3.0.
 * IF() takes the type of its operands, so `IF(was_in_promo = 1, promo_price, price)` was a text for every line, and
 * ROUND() of a text reads it as a floating point number: 63.325 rounded to 63.32, where the DECIMAL rounds it to 63.33.
 *
 * The total of an order is computed on read. An order placed before the conversion has to keep the total it was invoiced
 * with, which is what `last_text_promo_price_order_id`, written by the conversion, is for. The tests run on a DECIMAL
 * column: reading it as a text reproduces the old type, so that no schema change is needed.
 */
final class OrderTextPromoPriceTest extends IntegrationTestCase
{
    private const THRESHOLD = 'last_text_promo_price_order_id';

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        // ConfigQuery caches in a static array the transaction rollback cannot reach.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    /** 63.325 + 9.95 of postage: the text reading gives 73.27, the DECIMAL 73.28. */
    public function testAnOrderAtTheThresholdKeepsTheTotalOfItsInvoice(): void
    {
        $order = $this->orderWithPromotionalLine('63.325000');

        ConfigQuery::write(self::THRESHOLD, (string) $order->getId());

        self::assertEqualsWithDelta(73.27, $order->getTotalAmount(), 0.0001);
    }

    public function testAnOrderAboveTheThresholdReadsTheDecimal(): void
    {
        $order = $this->orderWithPromotionalLine('63.325000');

        ConfigQuery::write(self::THRESHOLD, (string) ($order->getId() - 1));

        self::assertEqualsWithDelta(73.28, $order->getTotalAmount(), 0.0001);
    }

    public function testAShopWithoutThresholdReadsTheDecimal(): void
    {
        $order = $this->orderWithPromotionalLine('63.325000');

        self::assertEqualsWithDelta(73.28, $order->getTotalAmount(), 0.0001);
    }

    /**
     * The text type was the one of the whole expression: the price of a line that is not on promotion was rounded as a
     * floating point number too, and 63.325 gave 63.32.
     */
    public function testALineThatIsNotOnPromotionIsFrozenTheSameWay(): void
    {
        $order = $this->factory->order(null, ['postage' => '9.95']);
        $this->factory->orderProduct($order, ['price' => '63.325000']);

        self::assertEqualsWithDelta(73.28, $order->getTotalAmount(), 0.0001);

        ConfigQuery::write(self::THRESHOLD, (string) $order->getId());

        // The totals of an order are read once per mode and threshold: a new total, not the one cached just above.
        self::assertEqualsWithDelta(73.27, $order->getTotalAmount(), 0.0001);
    }

    /** 12.345 x 7: 12.34 per unit as a text, 12.35 as a decimal. */
    public function testTheUnitPriceIsRoundedBeforeTheQuantityLikeItWas(): void
    {
        $order = $this->factory->order();
        $line = $this->factory->orderProduct($order, ['quantity' => 7.0]);
        $line->setWasInPromo(1)->setPromoPrice('12.345000')->save();

        ConfigQuery::write(self::THRESHOLD, (string) $order->getId());

        self::assertEqualsWithDelta(86.38, $order->getTotalAmount(), 0.0001);

        $unfrozen = $this->factory->order();
        $unfrozenLine = $this->factory->orderProduct($unfrozen, ['quantity' => 7.0]);
        $unfrozenLine->setWasInPromo(1)->setPromoPrice('12.345000')->save();

        self::assertEqualsWithDelta(86.45, $unfrozen->getTotalAmount(), 0.0001);
    }

    /**
     * Under rounding of sums the quantity is a floating point number, which makes the product a floating point number
     * whatever the type of the price: both readings agree, and the frozen query has to run all the same.
     */
    public function testTheFrozenReadingRunsUnderRoundingOfSums(): void
    {
        ConfigQuery::write('order_rounding_mode', (string) ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS);

        $order = $this->factory->order();
        $line = $this->factory->orderProduct($order, ['quantity' => 3.0]);
        $line->setWasInPromo(1)->setPromoPrice('63.325000')->save();

        ConfigQuery::write(self::THRESHOLD, (string) $order->getId());

        self::assertEqualsWithDelta(189.98, $order->getTotalAmount(), 0.0001);
    }

    /** An order of the 2.4 upgrade is totalled without any rounding, and the frozen reading keeps it so. */
    public function testTheFrozenReadingRunsForAnOrderTotalledWithoutRounding(): void
    {
        $order = $this->orderWithPromotionalLine('63.325000');

        ConfigQuery::write('last_legacy_rounding_order_id', (string) $order->getId());
        ConfigQuery::write(self::THRESHOLD, (string) $order->getId());

        self::assertEqualsWithDelta(73.275, $order->getTotalAmount(), 0.0001);
    }

    /**
     * A promotional line whose text was empty is NULL once converted, and NULL plus a tax is NULL: the line would leave
     * the total. It counted for 0 plus its tax, and the frozen reading keeps it so: 12.67 of tax and 9.95 of postage.
     */
    public function testAFrozenPromotionalLineWithoutPriceIsCountedForZeroPlusItsTax(): void
    {
        $order = $this->orderWithPromotionalLine('63.325000');
        $line = $order->getOrderProducts()->getFirst();
        (new OrderProductTax())
            ->setOrderProductId($line->getId())
            ->setTitle('VAT')
            ->setDescription('')
            ->setAmount('13.331667')
            ->setPromoAmount('12.665000')
            ->save();
        $this->getPropelConnection()->exec('UPDATE order_product SET promo_price = NULL WHERE id = '.(int) $line->getId());

        ConfigQuery::write(self::THRESHOLD, (string) $order->getId());

        self::assertEqualsWithDelta(22.62, $order->getTotalAmount(), 0.0001);
    }

    private function orderWithPromotionalLine(string $promoPrice): Order
    {
        $order = $this->factory->order(null, ['postage' => '9.95']);
        $line = $this->factory->orderProduct($order, ['price' => '66.658333']);
        $line->setWasInPromo(1)->setPromoPrice($promoPrice)->save();

        return $order;
    }
}
