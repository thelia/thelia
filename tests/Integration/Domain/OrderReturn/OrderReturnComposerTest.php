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

use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\Service\OrderReturnComposer;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct as OrderProductModel;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * What a return being opened is worth, line by line, and the lock it takes on
 * the lines it checks.
 */
final class OrderReturnComposerTest extends IntegrationTestCase
{
    use SecondConnectionTrait;

    private OrderReturnComposer $composer;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deleteTheLinesAKilledTestLeft();
        $this->composer = $this->getService(OrderReturnComposer::class);
        $this->factory = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        OrderReturnStatusQuery::resetCache();

        try {
            parent::tearDown();
        } finally {
            // Once the test transaction is rolled back, which is what releases the
            // lock the test took on them, and even when the teardown fails.
            $this->deleteTheCommittedLines();
        }
    }

    public function testThePostageCannotBeReturnedTwiceByTwoOpenReturns(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID, 'postage' => '6.000000']);

        (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_REQUESTED))
            ->setIncludePostage(true)
            ->save($this->getPropelConnection());

        $this->expectException(ReturnNotAllowedException::class);
        $this->composer->postageRefund($order);
    }

    public function testEachRequestedLineIsPricedForItsOwnQuantity(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $first = $this->orderProduct($order, price: '10.000000', quantity: 2.0);
        $second = $this->orderProduct($order, price: '25.000000', quantity: 1.0);

        self::assertSame(
            [20.0, 25.0],
            $this->composer->priceRequestedLines($order, $customer, [
                ['order_product' => $first, 'quantity' => 2.0],
                ['order_product' => $second, 'quantity' => 1.0],
            ]),
        );
    }

    /**
     * Two requests for the same line must not both read the quantity still
     * returnable before either has written its return. The line is committed
     * from a connection of its own, so that this second connection, standing
     * for the second request, sees it and can try to take it.
     */
    public function testPricingALineHoldsItAgainstASecondRequest(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $line = $this->committedOrderProduct($order, quantity: 2.0);

        self::assertTrue($this->aSecondRequestCanTakeTheLine($line), 'Guard: nothing holds the line yet.');

        self::assertSame(
            [10.0],
            $this->composer->priceRequestedLines($order, $customer, [['order_product' => $line, 'quantity' => 1.0]]),
        );

        self::assertFalse($this->aSecondRequestCanTakeTheLine($line), 'A second request took a line the first one holds.');
    }

    private function orderProduct(Order $order, string $price, float $quantity): OrderProductModel
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
            ->setVirtual(0);
        $orderProduct->save($this->getPropelConnection());

        return $orderProduct;
    }
}
