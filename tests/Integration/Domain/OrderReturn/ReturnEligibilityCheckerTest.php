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
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct as OrderProductModel;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The one gate every entry point (Flexy, the front API, the back-office) goes
 * through to decide whether a return may be opened, and for which quantity.
 */
final class ReturnEligibilityCheckerTest extends IntegrationTestCase
{
    private ReturnEligibilityChecker $checker;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = $this->getService(ReturnEligibilityChecker::class);
        $this->factory = $this->createFixtureFactory();

        ConfigQuery::write('order_return_enabled', '1');
        ConfigQuery::write('order_return_window_days', '14');
    }

    protected function tearDown(): void
    {
        // ConfigQuery keeps a static cache the transaction rollback cannot reach.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testAnEligibleOrderCanBeOpened(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();

        $this->checker->assertOrderReturnable($order, $customer);

        self::assertTrue($this->checker->isReturnable($order));
    }

    public function testTheDisabledFeatureBlocksOpening(): void
    {
        ConfigQuery::write('order_return_enabled', '0');
        [$order, $customer] = $this->paidOrderWithProduct();

        self::assertFalse($this->checker->isReturnable($order));

        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertOrderReturnable($order, $customer);
    }

    public function testAnOrderOutsideTheWindowBlocksOpening(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct(placedDaysAgo: 30);

        self::assertFalse($this->checker->isWithinReturnWindow($order));
        self::assertFalse($this->checker->isReturnable($order));

        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertOrderReturnable($order, $customer);
    }

    public function testAnUnpaidOrderIsNotWithinTheWindow(): void
    {
        [$order] = $this->paidOrderWithProduct(statusCode: OrderStatus::CODE_NOT_PAID);

        self::assertFalse($this->checker->isWithinReturnWindow($order));
    }

    public function testAForeignOrderCannotBeOpenedByAnotherCustomer(): void
    {
        [$order] = $this->paidOrderWithProduct();
        $intruder = $this->factory->customer($this->factory->customerTitle());

        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertOrderReturnable($order, $intruder);
    }

    public function testAVirtualProductIsNeverReturnable(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();
        $virtual = $this->orderProduct($order, quantity: 1.0, virtual: true);

        self::assertFalse($this->checker->isProductReturnable($virtual));

        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertReturnable($order, $customer, $virtual, 1.0);
    }

    public function testAQuantityAboveTheOrderedOneIsRejected(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();
        $line = $this->orderProduct($order, quantity: 2.0);

        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertReturnable($order, $customer, $line, 3.0);
    }

    public function testOpenReturnsConsumeTheReturnableQuantity(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();
        $line = $this->orderProduct($order, quantity: 3.0);

        self::assertSame(3.0, $this->checker->remainingReturnableQuantity($line));

        $this->openReturn($order, $customer, $line, 2.0, OrderReturnStatus::CODE_REQUESTED);

        self::assertSame(1.0, $this->checker->remainingReturnableQuantity($line));

        // One unit left: asking for two is refused.
        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertReturnable($order, $customer, $line, 2.0);
    }

    public function testARefusedReturnFreesItsQuantity(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();
        $line = $this->orderProduct($order, quantity: 3.0);

        $this->openReturn($order, $customer, $line, 3.0, OrderReturnStatus::CODE_REFUSED);

        // A refused return holds nothing: the whole quantity is returnable again.
        self::assertSame(3.0, $this->checker->remainingReturnableQuantity($line));
    }

    /**
     * A quantity is a FLOAT - Thelia sells by weight and by length as well as
     * by the piece - so the remaining quantity is a subtraction of floats:
     * 0.7 ordered minus 0.3 already asked for is 0.39999999999999997, and the
     * customer asking for the 0.4 the shop displays used to be refused.
     */
    public function testTheRemainderOfAFloatQuantityIsStillReturnable(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();
        $line = $this->storedOrderProduct($this->orderProduct($order, quantity: 0.7));

        $this->openReturn($order, $customer, $line, 0.3, OrderReturnStatus::CODE_REQUESTED);

        self::assertSame(0.4, $this->checker->remainingReturnableQuantity($line));

        $this->checker->assertReturnable($order, $customer, $line, 0.4);
    }

    /**
     * The tolerance the float subtraction needs must not become an extra unit
     * handed out on a line that is already entirely returned.
     */
    public function testAFullyReturnedFloatLineHasNothingLeft(): void
    {
        [$order, $customer] = $this->paidOrderWithProduct();
        $line = $this->storedOrderProduct($this->orderProduct($order, quantity: 0.7));

        $this->openReturn($order, $customer, $line, 0.3, OrderReturnStatus::CODE_REQUESTED);
        $this->openReturn($order, $customer, $line, 0.4, OrderReturnStatus::CODE_REQUESTED);

        self::assertSame(0.0, $this->checker->remainingReturnableQuantity($line));

        $this->expectException(ReturnNotAllowedException::class);
        $this->checker->assertReturnable($order, $customer, $line, 0.1);
    }

    /**
     * @return array{Order, Customer}
     */
    private function paidOrderWithProduct(
        string $statusCode = OrderStatus::CODE_PAID,
        int $placedDaysAgo = 1,
    ): array {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $order = $this->factory->order($customer, ['statusCode' => $statusCode]);

        $order->setCreatedAt(new \DateTime(\sprintf('-%d days', $placedDaysAgo)));
        $order->save($this->getPropelConnection());

        $this->orderProduct($order, quantity: 1.0);

        return [$order, $customer];
    }

    private function orderProduct(Order $order, float $quantity, bool $virtual = false): OrderProductModel
    {
        $orderProduct = (new OrderProductModel())
            ->setOrderId((int) $order->getId())
            ->setProductRef('REF-'.uniqid())
            ->setProductSaleElementsRef('PSE-'.uniqid())
            ->setProductSaleElementsId(1)
            ->setTitle('A returnable product')
            ->setQuantity($quantity)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(1)
            ->setWasInPromo(0)
            ->setVirtual($virtual ? 1 : 0);
        $orderProduct->save($this->getPropelConnection());

        return $orderProduct;
    }

    /**
     * The same line read back from the database, so that the quantity carries
     * the precision the FLOAT column actually stores and not the double the
     * fixture set.
     */
    private function storedOrderProduct(OrderProductModel $orderProduct): OrderProductModel
    {
        $stored = OrderProductQuery::create()->findPk($orderProduct->getId(), $this->getPropelConnection());

        self::assertNotNull($stored);

        return $stored;
    }

    private function openReturn(
        Order $order,
        Customer $customer,
        OrderProductModel $orderProduct,
        float $quantity,
        string $statusCode,
    ): OrderReturn {
        $status = OrderReturnStatusQuery::create()->findOneByCode($statusCode);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus($status);
        $return->save($this->getPropelConnection());

        (new OrderReturnLine())
            ->setOrderReturn($return)
            ->setOrderProduct($orderProduct)
            ->setQuantity($quantity)
            ->save($this->getPropelConnection());

        return $return;
    }
}
