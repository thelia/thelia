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

namespace Thelia\Tests\Integration\Domain\OrderReturn;

use Thelia\Domain\OrderReturn\Service\RefundAmountCalculator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct as OrderProductModel;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * What a return is worth has to be what the customer was charged, and an order
 * discount - a coupon, a merchant rebate - is charged on the order, not on its
 * lines: an order of 84 paid 63 keeps lines totalling 84. Refunding the lines
 * at face value hands back the full 84.
 */
final class RefundAmountCalculatorTest extends IntegrationTestCase
{
    private RefundAmountCalculator $calculator;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = $this->getService(RefundAmountCalculator::class);
        $this->factory = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        // ConfigQuery keeps a static cache the transaction rollback cannot reach.
        ConfigQuery::resetCache();
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    public function testReturningAWholeDiscountedOrderRefundsWhatItWasChargedFor(): void
    {
        $order = $this->discountedOrder();
        $first = $this->orderProduct($order, price: '30.000000', quantity: 2.0, unitTax: '6.000000');
        $second = $this->orderProduct($order, price: '10.000000', quantity: 1.0, unitTax: '2.000000');

        $return = $this->returnOf($order, [[$first, 2.0], [$second, 1.0]]);

        $charged = 0.0;
        $chargedForTheLines = $order->getTotalAmount($charged, includePostage: false);

        self::assertSame(63.0, round($chargedForTheLines, 2), 'The fixture is not the discounted order this test needs.');
        self::assertSame(63.0, round($this->calculator->compute($return), 2));
    }

    public function testALineOfADiscountedOrderRefundsItsShareOfTheDiscountOnly(): void
    {
        $order = $this->discountedOrder();
        $first = $this->orderProduct($order, price: '30.000000', quantity: 2.0, unitTax: '6.000000');
        $this->orderProduct($order, price: '10.000000', quantity: 1.0, unitTax: '2.000000');

        // One unit of a line worth 36 taxed, in an order whose lines total 84
        // and which carries a discount of 21: 36 - 21 * 36 / 84.
        self::assertSame(27.0, round($this->calculator->lineRefundForProduct($first, 1.0), 2));
    }

    /**
     * A promotion may put a line in the order at no charge. Nothing was paid
     * for it, so nothing is refunded for it - and the discount it carries no
     * share of must not turn the refund negative.
     */
    public function testAnOfferedLineRefundsNothingAndTakesNoShareOfTheDiscount(): void
    {
        $order = $this->discountedOrder();
        $paid = $this->orderProduct($order, price: '30.000000', quantity: 2.0, unitTax: '6.000000');
        $offered = $this->orderProduct($order, price: '0.000000', quantity: 1.0, unitTax: '0.000000', offered: true);
        $this->orderProduct($order, price: '10.000000', quantity: 1.0, unitTax: '2.000000');

        self::assertSame(0.0, $this->calculator->lineRefundForProduct($offered, 1.0));

        $return = $this->returnOf($order, [[$paid, 1.0], [$offered, 1.0]]);

        self::assertSame(27.0, round($this->calculator->compute($return), 2));
    }

    public function testAnUndiscountedOrderRefundsTheFullLinePrice(): void
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $orderProduct = $this->orderProduct($order, price: '30.000000', quantity: 2.0, unitTax: '6.000000');

        self::assertSame(72.0, round($this->calculator->lineRefundForProduct($orderProduct, 2.0), 2));
    }

    /**
     * An order whose lines total less than the discount recorded on it - the
     * core clamps such a total at zero rather than paying the customer.
     */
    public function testADiscountWiderThanTheOrderRefundsNothing(): void
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $order->setDiscount('500.000000');
        $order->save($this->getPropelConnection());

        $orderProduct = $this->orderProduct($order, price: '30.000000', quantity: 1.0, unitTax: '0.000000');

        self::assertSame(0.0, round($this->calculator->lineRefundForProduct($orderProduct, 1.0), 2));
    }

    private function discountedOrder(): Order
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $order->setDiscount('21.000000');
        $order->save($this->getPropelConnection());

        return $order;
    }

    private function orderProduct(Order $order, string $price, float $quantity, string $unitTax, bool $offered = false): OrderProductModel
    {
        $orderProduct = (new OrderProductModel())
            ->setOrderId((int) $order->getId())
            ->setProductRef('REF-'.uniqid())
            ->setProductSaleElementsRef('PSE-'.uniqid())
            ->setProductSaleElementsId(1)
            ->setTitle('A returnable product')
            ->setQuantity($quantity)
            ->setPrice($price)
            ->setPromoPrice($price)
            ->setWasNew(1)
            ->setWasInPromo(0)
            ->setVirtual(0)
            ->setIsOffered($offered ? 1 : 0);
        $orderProduct->save($this->getPropelConnection());

        if ('0.000000' !== $unitTax) {
            (new OrderProductTax())
                ->setOrderProductId((int) $orderProduct->getId())
                ->setTitle('VAT')
                ->setAmount($unitTax)
                ->setPromoAmount($unitTax)
                ->save($this->getPropelConnection());
        }

        // The order totals its lines in SQL and keeps the answer in a static
        // cache keyed by order: a line added after a first read would never be
        // counted.
        $order->clearOrderProducts();

        return $orderProduct;
    }

    /**
     * @param list<array{0: OrderProductModel, 1: float}> $lines
     */
    private function returnOf(Order $order, array $lines): OrderReturn
    {
        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($order->getCustomer())
            ->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_REQUESTED));
        $return->save($this->getPropelConnection());

        foreach ($lines as [$orderProduct, $quantity]) {
            (new OrderReturnLine())
                ->setOrderReturn($return)
                ->setOrderProduct($orderProduct)
                ->setQuantity($quantity)
                ->save($this->getPropelConnection());
        }

        return $return;
    }
}
