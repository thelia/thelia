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

namespace Thelia\Tests\Integration\Domain\Order;

use Thelia\Action\OrderReturn as OrderReturnAction;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\OrderReturn\OrderReturnEvent;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Order\Service\StockDecrementer;
use Thelia\Domain\Order\StatusAction\Effect\AdjustStockAction;
use Thelia\Domain\Order\StatusAction\OrderStatusActionContext;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\Service\StockIncrementer;
use Thelia\Domain\SaleUnit\Stock\SaleUnitStockResolver;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\Product;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;
use Thelia\Tests\Support\Order\MovesOrders;
use Thelia\Tests\Support\Order\PlacesBundleOrders;
use Thelia\Tests\Support\Order\RecordingStockDecrementer;
use Thelia\Tests\Support\Order\RecordsStatements;

/**
 * What moves the stock of an order after it is placed, for an order made of a pack: a
 * cancellation, a payment, a refund, the adjustment a status asks for, a return. The pack
 * holds no stock, so none of them may take from it or give back to it, and its components
 * come and go as the products they are.
 *
 * The pack of the plan: bike (1, stock 7), helmet (2, stock 5) and lock (1 to 3, stock 10),
 * ordered with two locks: 7, 5 and 10 become 6, 3 and 8.
 */
final class BundleOrderStockTest extends ActionIntegrationTestCase
{
    use BuildsBundles;
    use MovesOrders;
    use PlacesBundleOrders;
    use RecordsStatements;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check_available_stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        ConfigQuery::write('order_return_enabled', '1');
        ConfigQuery::write('order_return_window_days', '14');
        ConfigQuery::write(OrderReturnAction::RESTOCK_MODE_CONFIG_KEY, OrderReturnAction::RESTOCK_MODE_RESELLABLE);
        ConfigQuery::resetCache();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        ConfigQuery::resetCache();
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    public function testCancellingAnOrderTakenAtItsPlacementGivesTheComponentsBack(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        [$example, $order] = $this->orderOfThePlan();

        self::assertSame([6.0, 3.0, 8.0], $this->stocksOf($example));

        $order->setCancelled($this->dispatcher);

        self::assertSame([7.0, 5.0, 10.0], $this->stocksOf($example), 'The components are back to what they were.');
        self::assertSame(0.0, $this->stockOfProduct($example['bundle']), 'The pack was never given anything back: its sale element holds no stock.');
    }

    public function testRefundingAnOrderTakenAtItsPlacementGivesTheComponentsBack(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        [$example, $order] = $this->orderOfThePlan();

        self::assertSame([6.0, 3.0, 8.0], $this->stocksOf($example));

        $this->moveOrderTo($order, OrderStatus::CODE_REFUNDED);

        self::assertSame([7.0, 5.0, 10.0], $this->stocksOf($example), 'The components are back to what they were.');
        self::assertSame(0.0, $this->stockOfProduct($example['bundle']), 'The pack was never given anything back: its sale element holds no stock.');
    }

    public function testRefundingAPaidOrderTakenAtItsPaymentGivesTheComponentsBack(): void
    {
        [$example, $order] = $this->orderOfThePlan();
        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertSame([6.0, 3.0, 8.0], $this->stocksOf($example));

        $this->moveOrderTo($order, OrderStatus::CODE_REFUNDED);

        self::assertSame([7.0, 5.0, 10.0], $this->stocksOf($example));
        self::assertSame(0.0, $this->stockOfProduct($example['bundle']));
    }

    public function testPayingThenUnpayingAnOrderTakenAtItsPaymentMovesTheComponentsOnly(): void
    {
        [$example, $order] = $this->orderOfThePlan();

        self::assertSame([7.0, 5.0, 10.0], $this->stocksOf($example), 'Nothing is taken before the payment.');

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertSame([6.0, 3.0, 8.0], $this->stocksOf($example));
        self::assertSame(0.0, $this->stockOfProduct($example['bundle']));

        $event = (new OrderEvent($order))->setStatus($this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId());
        $event->forceStatusTransition();
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertSame([7.0, 5.0, 10.0], $this->stocksOf($example), 'A paid order that becomes unpaid gives its components back.');
        self::assertSame(0.0, $this->stockOfProduct($example['bundle']));
    }

    public function testPayingAnOrderWhoseBundleSaleElementIsAtZeroIsNotRefusedForWantOfStock(): void
    {
        [$example, $order] = $this->orderOfThePlan();
        self::assertSame(0.0, $this->stockOfProduct($example['bundle']));

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertTrue($order->isPaid(false), 'The payment of a pack does not look for stock in the sale element of the pack.');
    }

    public function testTheAdjustmentOfAStatusMovesTheComponentsAndLeavesTheBundleAlone(): void
    {
        [$example, $order] = $this->orderOfThePlan();
        $action = new AdjustStockAction($this->getService(StockDecrementer::class));

        $action->execute($this->actionContextFor($order, AdjustStockAction::OPERATION_DECREASE));

        self::assertSame([6.0, 3.0, 8.0], $this->stocksOf($example));
        self::assertSame(0.0, $this->stockOfProduct($example['bundle']));

        $action->execute($this->actionContextFor($order, AdjustStockAction::OPERATION_INCREASE));

        self::assertSame([7.0, 5.0, 10.0], $this->stocksOf($example));
        self::assertSame(0.0, $this->stockOfProduct($example['bundle']), 'Giving back must not put a stock on the pack either.');
    }

    public function testTheLineOfAComponentCannotBeReturnedOnItsOwn(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        [$example, $order, $customer] = $this->orderOfThePlan(withCustomer: true);
        $this->payTheOrder($order);
        [, $componentLine] = $this->linesOf($order);

        $return = $this->pendingReturn($customer, $order, $componentLine, 1.0);

        $this->expectException(ReturnNotAllowedException::class);
        $this->expectExceptionMessage('This item is part of a bundle and cannot be returned on its own.');
        $this->dispatch(new OrderReturnEvent($return), TheliaEvents::ORDER_RETURN_CREATE);
    }

    public function testReceivingTheReturnOfABundleLinePutsItsComponentsBackInProportion(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($this->factory, $service);
        $example['helmetProduct']->getDefaultSaleElements()->setQuantity(10)->save();

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        // Three packs, each a bike, two helmets and two locks: 3, 6 and 6 taken out.
        $this->addBundleLine($cart, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2', (int) $example['lock']->getId() => '2'], 3.0);
        $order = $this->placeOrder($cart, $customer, $currency, $lang);
        $this->payTheOrder($order);

        self::assertSame([4.0, 4.0, 4.0], $this->stocksOf($example));

        [$bundleLine] = $this->linesOf($order);
        $return = $this->pendingReturn($customer, $order, $bundleLine, 2.0);
        $this->dispatch(new OrderReturnEvent($return), TheliaEvents::ORDER_RETURN_CREATE);

        self::assertSame(2 * 540.0, (float) $return->getRefundAmount(), 'Two packs at 540.00, no tax rule rate on this order.');

        $accepted = OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_ACCEPTED);
        $return->setOrderReturnStatus($accepted)->save();
        $line = $return->getOrderReturnLines()->getFirst();

        $this->dispatch(
            (new OrderReturnEvent($return))->setReceivedLines([(int) $line->getId() => ['quantity' => 2.0, 'condition' => 'as new', 'resellable' => true]]),
            TheliaEvents::ORDER_RETURN_RECEIVE,
        );

        self::assertSame([6.0, 8.0, 8.0], $this->stocksOf($example), 'Two packs back out of three: two bikes, four helmets, four locks.');
        self::assertSame(0.0, $this->stockOfProduct($example['bundle']), 'The pack holds no stock to give back to.');
    }

    public function testAReturnedBundleThatIsNotResellableGivesNothingBack(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        [$example, $order, $customer] = $this->orderOfThePlan(withCustomer: true);
        $this->payTheOrder($order);
        [$bundleLine] = $this->linesOf($order);

        $return = $this->pendingReturn($customer, $order, $bundleLine, 1.0);
        $this->dispatch(new OrderReturnEvent($return), TheliaEvents::ORDER_RETURN_CREATE);
        $return->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_ACCEPTED))->save();
        $line = $return->getOrderReturnLines()->getFirst();

        $this->dispatch(
            (new OrderReturnEvent($return))->setReceivedLines([(int) $line->getId() => ['quantity' => 1.0, 'resellable' => false]]),
            TheliaEvents::ORDER_RETURN_RECEIVE,
        );

        self::assertSame([6.0, 3.0, 8.0], $this->stocksOf($example));
    }

    public function testCancellingAnOrderGivesTheStockBackInTheAscendingIdOfTheSaleElements(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        [$order, $helmet, $bike] = $this->orderOfAPackWhoseFirstComponentHasTheHigherId();

        $updated = $this->stockRowsUpdatedDuring(fn () => $order->setCancelled($this->dispatcher));

        self::assertSame([$this->saleElementsIdOf($helmet), $this->saleElementsIdOf($bike)], $updated, 'The rows are locked by ascending id, as the order took them.');
    }

    public function testTheAdjustmentOfAStatusMovesTheStockInTheAscendingIdOfTheSaleElements(): void
    {
        [$order, $helmet, $bike] = $this->orderOfAPackWhoseFirstComponentHasTheHigherId();
        $decrementer = new RecordingStockDecrementer($this->getService(SaleUnitStockResolver::class));
        $action = new AdjustStockAction($decrementer);

        $action->execute($this->actionContextFor($order, AdjustStockAction::OPERATION_DECREASE));
        $action->execute($this->actionContextFor($order, AdjustStockAction::OPERATION_INCREASE));

        self::assertSame([$this->saleElementsIdOf($helmet), $this->saleElementsIdOf($bike)], $decrementer->decrementedIds());
        self::assertSame([$this->saleElementsIdOf($helmet), $this->saleElementsIdOf($bike)], $decrementer->incrementedIds());
    }

    public function testReceivingAReturnGivesTheStockBackInTheAscendingIdOfTheSaleElements(): void
    {
        $this->manageTheStockWhenTheOrderIsPlaced();
        [$order, $helmet, $bike, $customer, $lock] = $this->orderOfAPackWhoseFirstComponentHasTheHigherId(withASimpleLineFirst: true);
        $this->payTheOrder($order);
        [$simpleLine, $bundleLine] = $this->linesOf($order);

        $return = $this->pendingReturn($customer, $order, $simpleLine, 1.0);
        $return->addOrderReturnLine((new OrderReturnLine())->setOrderProduct($bundleLine)->setQuantity(1.0));
        $this->dispatch(new OrderReturnEvent($return), TheliaEvents::ORDER_RETURN_CREATE);
        $return->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_ACCEPTED))->save();
        $received = [];
        foreach ($return->getOrderReturnLines() as $line) {
            $received[(int) $line->getId()] = ['quantity' => 1.0, 'resellable' => true];
        }

        // The action of the container, with every collaborator it has but the stock it gives back to.
        $decrementer = new RecordingStockDecrementer($this->getService(SaleUnitStockResolver::class));
        $collaborators = (fn (): array => get_object_vars($this))->call($this->getService(OrderReturnAction::class));
        $collaborators['stockIncrementer'] = new StockIncrementer($decrementer);
        $action = new OrderReturnAction(...$collaborators);

        $action->receive((new OrderReturnEvent($return))->setReceivedLines($received), TheliaEvents::ORDER_RETURN_RECEIVE, $this->dispatcher);

        self::assertSame(
            [$this->saleElementsIdOf($helmet), $this->saleElementsIdOf($bike), $this->saleElementsIdOf($lock)],
            $decrementer->incrementedIds(),
            'The plain line and the components of the pack, all in the ascending id of their sale element.',
        );
    }

    public function testReceivingAReturnWithoutAPackReadsTheLineOfEachProductOnceAsBefore(): void
    {
        $this->exampleBundle($this->factory, $this->getService(BundleCompositionService::class));
        $this->manageTheStockWhenTheOrderIsPlaced();
        $first = $this->component($this->factory, '10', 5);
        $second = $this->component($this->factory, '20', 5);
        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addSimpleLine($cart, $first, 2.0);
        $this->addSimpleLine($cart, $second, 2.0);
        $order = $this->placeOrder($cart, $customer, $currency, $lang);
        $this->payTheOrder($order);
        [$firstLine, $secondLine] = $this->linesOf($order);

        $return = $this->pendingReturn($customer, $order, $firstLine, 1.0);
        $return->addOrderReturnLine((new OrderReturnLine())->setOrderProduct($secondLine)->setQuantity(1.0));
        $this->dispatch(new OrderReturnEvent($return), TheliaEvents::ORDER_RETURN_CREATE);
        $return->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_ACCEPTED))->save();
        $received = [];
        foreach ($return->getOrderReturnLines() as $line) {
            $received[(int) $line->getId()] = ['quantity' => 1.0, 'resellable' => true];
        }

        $statements = $this->statementsDuring(fn () => $this->dispatch((new OrderReturnEvent($return))->setReceivedLines($received), TheliaEvents::ORDER_RETURN_RECEIVE));

        self::assertSame([4.0, 4.0], [$this->stockOfProduct($first), $this->stockOfProduct($second)]);
        self::assertCount(2, array_filter(
            $statements,
            static fn (string $statement): bool => str_starts_with($statement, 'SELECT') && str_contains($statement, 'FROM `order_product` WHERE `id` = ') && !str_contains($statement, 'FOR UPDATE'),
        ), 'The line of each product is read once, for its refund: telling a pack apart costs nothing more.');
    }

    public function testAnOrderPlacedBeforeBundlesExistedIsReadAndMovedAsBefore(): void
    {
        $product = $this->component($this->factory, '10', 10);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_NOT_PAID]);
        $pseId = $this->saleElementsIdOf($product);
        // A line as every order placed before the column of the line type was written: a product, no parent.
        $line = $this->factory->orderProduct($order, ['productSaleElementsId' => $pseId, 'quantity' => 2.0, 'price' => '10.000000', 'promoPrice' => '10.000000']);
        $line->setWeight('1.5')->save();

        self::assertNull($line->getParent());
        self::assertTrue($line->isProductLine());
        self::assertTrue($line->isShippableLine());
        self::assertTrue($line->movesStockOfItsOwn());
        self::assertSame(3.0, $order->getWeight());
        $tax = 0.0;
        self::assertSame(20.0, $order->getTotalAmount($tax, false, false), 'The invoice of an order without a pack is what it always was.');

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);
        self::assertSame(8.0, $this->stockOfProduct($product), 'Paid: the line takes its stock.');

        $event = (new OrderEvent($order))->setStatus($this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId());
        $event->forceStatusTransition();
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
        self::assertSame(10.0, $this->stockOfProduct($product), 'Unpaid again: the line gives it back.');

        $checker = $this->getService(\Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker::class);
        self::assertTrue($checker->isProductReturnable($line));
    }

    /**
     * @return array{0: array<string, mixed>, 1: Order, 2?: Customer}
     */
    private function orderOfThePlan(bool $withCustomer = false): array
    {
        $service = $this->getService(BundleCompositionService::class);
        $example = $this->exampleBundle($this->factory, $service);

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->addBundleLine($cart, $example['bundle'], [(int) $example['bike']->getId() => '1', (int) $example['helmet']->getId() => '2', (int) $example['lock']->getId() => '2']);
        $order = $this->placeOrder($cart, $customer, $currency, $lang);

        return $withCustomer ? [$example, $order, $customer] : [$example, $order];
    }

    /**
     * A pack whose components are listed bike first, helmet second, while the sale element of the
     * helmet has the lower id; with a plain line of a lock, of the highest id, written first.
     *
     * @return array{0: Order, 1: Product, 2: Product, 3: Customer, 4: Product|null} the order, the helmet, the bike, the buyer, the lock
     */
    private function orderOfAPackWhoseFirstComponentHasTheHigherId(bool $withASimpleLineFirst = false): array
    {
        $service = $this->getService(BundleCompositionService::class);
        $helmet = $this->component($this->factory, '30', 10);
        $bike = $this->component($this->factory, '500', 10);
        $lock = $this->component($this->factory, '20', 10);
        $pack = $this->bundleProduct($this->factory, $service);
        $bikeItem = $service->addItem((int) $pack->getId(), $this->saleElementsIdOf($bike), '1');
        $helmetItem = $service->addItem((int) $pack->getId(), $this->saleElementsIdOf($helmet), '1');

        [$customer, $currency, $lang] = $this->aBuyer();
        $cart = $this->aCart($customer, $currency);

        if ($withASimpleLineFirst) {
            $this->addSimpleLine($cart, $lock);
        }

        $this->addBundleLine($cart, $pack, [(int) $bikeItem->getId() => '1', (int) $helmetItem->getId() => '1']);

        return [$this->placeOrder($cart, $customer, $currency, $lang), $helmet, $bike, $customer, $withASimpleLineFirst ? $lock : null];
    }

    /**
     * @return list<int> the sale elements whose stock row the model updates, in the order of the statements
     */
    private function stockRowsUpdatedDuring(callable $run): array
    {
        $updated = [];
        $statements = $this->statementsDuring($run);

        foreach ($statements as $statement) {
            if (1 === preg_match('/^UPDATE `product_sale_elements` SET .* WHERE `product_sale_elements`.`id`=(\d+)$/s', $statement, $match)) {
                $updated[] = (int) $match[1];
            }
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $example
     *
     * @return list<float> the stock of the bike, the helmet and the lock
     */
    private function stocksOf(array $example): array
    {
        return array_map(
            fn (Product $product): float => $this->stockOfProduct($product),
            [$example['bikeProduct'], $example['helmetProduct'], $example['lockProduct']],
        );
    }

    private function payTheOrder(Order $order): void
    {
        $order->setStatusId($this->orderStatus(OrderStatus::CODE_PAID)->getId())->save();
    }

    private function pendingReturn(Customer $customer, Order $order, OrderProduct $orderProduct, float $quantity): OrderReturn
    {
        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setCreatedByAdmin(true);

        $return->addOrderReturnLine(
            (new OrderReturnLine())
                ->setOrderProduct($orderProduct)
                ->setQuantity($quantity),
        );

        return $return;
    }

    private function actionContextFor(Order $order, string $operation): OrderStatusActionContext
    {
        return new OrderStatusActionContext(
            $order,
            $this->orderStatus(OrderStatus::CODE_NOT_PAID),
            $this->orderStatus(OrderStatus::CODE_PROCESSING),
            [AdjustStockAction::FIELD_OPERATION => $operation],
        );
    }

    private function manageTheStockWhenTheOrderIsPlaced(): void
    {
        $listener = static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(true);
        $eventName = TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque');

        $this->dispatcher->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }
}
