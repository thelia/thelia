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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Event\Order\GetStockUpdateOperationOnOrderStatusChangeEvent;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Enum\OrderStatusActionTrigger;
use Thelia\Domain\Order\EventListener\StockOperationActionListener;
use Thelia\Domain\Order\StatusAction\Effect\AdjustStockAction;
use Thelia\Domain\Order\StatusAction\StockActionDiagnostics;
use Thelia\Domain\Order\StatusAction\StockOperationSimulator;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusAction;
use Thelia\Model\OrderStatusActionFailureQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\MovesOrders;

/**
 * How much stock a status change moves: the fixed rule of the core when no stock action
 * covers the change, the first matching stock action otherwise, and never both.
 */
final class StockOperationRuleTest extends ActionIntegrationTestCase
{
    use MovesOrders;

    /** @var array{string, callable}|null */
    private ?array $stockOnCreationListener = null;

    protected function tearDown(): void
    {
        if (null !== $this->stockOnCreationListener) {
            $this->dispatcher->removeListener(...$this->stockOnCreationListener);
        }

        ConfigQuery::resetCache();
        parent::tearDown();
    }

    public function testWithoutStockActionAnOrderThatBecomesPaidTakesItsProductsOutOfStock(): void
    {
        $this->stockIsManagedOnOrderCreation(false);
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf($productSaleElements, 3, OrderStatus::CODE_NOT_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertSame(7.0, $this->stockOf($productSaleElements));
    }

    public function testWithoutStockActionAnOrderWhoseStockLeftAtCreationDoesNotLeaveItAgainWhenPaid(): void
    {
        $this->stockIsManagedOnOrderCreation(true);
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf($productSaleElements, 3, OrderStatus::CODE_NOT_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertSame(10.0, $this->stockOf($productSaleElements));
    }

    public function testWithoutStockActionAPaidOrderThatIsCanceledPutsItsProductsBackInStock(): void
    {
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf($productSaleElements, 3, OrderStatus::CODE_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);

        self::assertSame(13.0, $this->stockOf($productSaleElements));
    }

    public function testWithoutStockActionAnUnpaidOrderCanceledPutsBackTheStockItTookAtCreation(): void
    {
        $this->stockIsManagedOnOrderCreation(true);
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf($productSaleElements, 3, OrderStatus::CODE_NOT_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);

        self::assertSame(13.0, $this->stockOf($productSaleElements));
    }

    public function testAStockActionOnAChangeTheCoreRuleAlreadyCoversMovesTheStockOnce(): void
    {
        $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_CANCELED, AdjustStockAction::OPERATION_INCREASE);
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf($productSaleElements, 3, OrderStatus::CODE_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);

        self::assertSame(13.0, $this->stockOf($productSaleElements), 'The stock action replaces the core rule on this change, it does not add to it.');
    }

    public function testAStockActionThatLeavesTheStockAloneStopsTheCoreRuleFromPuttingItBack(): void
    {
        $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_REFUNDED, AdjustStockAction::OPERATION_NONE);
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf($productSaleElements, 3, OrderStatus::CODE_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_REFUNDED);

        self::assertSame(10.0, $this->stockOf($productSaleElements), 'A refund without a return of the goods leaves the stock as it is.');
    }

    public function testAStockActionOfACanonicalStatusAppliesToACustomStatusEquivalentToIt(): void
    {
        $this->factory->orderStatus(['code' => 'canceled_by_customer', 'equivalentCode' => OrderStatus::CODE_CANCELED]);
        $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_CANCELED, AdjustStockAction::OPERATION_NONE);
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf($productSaleElements, 3, OrderStatus::CODE_PAID);

        $this->moveOrderTo($order, 'canceled_by_customer');

        self::assertSame(10.0, $this->stockOf($productSaleElements));
    }

    public function testAStockActionLimitedToAPaymentModuleOnlyAppliesToTheOrdersPaidWithIt(): void
    {
        $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_CANCELED, AdjustStockAction::OPERATION_NONE, limits: [AdjustStockAction::FIELD_PAYMENT_MODULES => ['Cheque']]);
        $paidByCheque = $this->createProductSaleElements(10);
        $paidOtherwise = $this->createProductSaleElements(10);
        $chequeOrder = $this->orderOf($paidByCheque, 3, OrderStatus::CODE_PAID);
        $otherOrder = $this->orderOf($paidOtherwise, 3, OrderStatus::CODE_PAID, ['paymentModuleCode' => 'FreeOrder']);

        $this->moveOrderTo($chequeOrder, OrderStatus::CODE_CANCELED);
        $this->moveOrderTo($otherOrder, OrderStatus::CODE_CANCELED);

        self::assertSame(10.0, $this->stockOf($paidByCheque), 'The action applies to the order paid by cheque.');
        self::assertSame(13.0, $this->stockOf($paidOtherwise), 'The other order follows the core rule.');
    }

    public function testAStockActionLimitedToADeliveryModuleOnlyAppliesToTheOrdersDeliveredByIt(): void
    {
        $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_CANCELED, AdjustStockAction::OPERATION_NONE, limits: [AdjustStockAction::FIELD_DELIVERY_MODULES => ['GeolocatedDelivery']]);
        $deliveredByIt = $this->createProductSaleElements(10);
        $deliveredOtherwise = $this->createProductSaleElements(10);
        $limitedOrder = $this->orderOf($deliveredByIt, 3, OrderStatus::CODE_PAID, ['deliveryModuleCode' => 'GeolocatedDelivery']);
        $otherOrder = $this->orderOf($deliveredOtherwise, 3, OrderStatus::CODE_PAID);

        $this->moveOrderTo($limitedOrder, OrderStatus::CODE_CANCELED);
        $this->moveOrderTo($otherOrder, OrderStatus::CODE_CANCELED);

        self::assertSame(10.0, $this->stockOf($deliveredByIt));
        self::assertSame(13.0, $this->stockOf($deliveredOtherwise));
    }

    public function testAStockActionLimitedToModulesThatAreGoneOrSwitchedOffIsIgnored(): void
    {
        ModuleQuery::create()->filterByCode('FreeOrder')->update(['Activate' => 0]);
        $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_CANCELED, AdjustStockAction::OPERATION_NONE, limits: [AdjustStockAction::FIELD_PAYMENT_MODULES => ['NoSuchModule', 'FreeOrder']]);
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf($productSaleElements, 3, OrderStatus::CODE_PAID, ['paymentModuleCode' => 'FreeOrder']);

        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);

        self::assertSame(13.0, $this->stockOf($productSaleElements), 'The limit names no active module: the action is ignored, the core rule applies.');
    }

    public function testAStockActionWhoseLimitNamesAGoneModuleIsIgnoredForItsOtherModulesToo(): void
    {
        $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_CANCELED, AdjustStockAction::OPERATION_NONE, limits: [AdjustStockAction::FIELD_PAYMENT_MODULES => ['FreeOrder', 'NoSuchModule']]);
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf($productSaleElements, 3, OrderStatus::CODE_PAID, ['paymentModuleCode' => 'FreeOrder']);

        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);

        self::assertSame(13.0, $this->stockOf($productSaleElements), 'FreeOrder is active, but the limit also names a module that is gone: the action is ignored, the core rule applies.');
    }

    public function testAStockActionDoesNotTakeOutAgainTheStockTakenWhenTheOrderWasPlaced(): void
    {
        $this->stockIsManagedOnOrderCreation(true);
        $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_PAID, AdjustStockAction::OPERATION_DECREASE);
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf($productSaleElements, 3, OrderStatus::CODE_NOT_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertSame(10.0, $this->stockOf($productSaleElements));
    }

    public function testAStockActionDoesNotPutBackAStockThePlacedOrderNeverTook(): void
    {
        $this->stockIsManagedOnOrderCreation(false);
        $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_CANCELED, AdjustStockAction::OPERATION_INCREASE);
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf($productSaleElements, 3, OrderStatus::CODE_NOT_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);

        self::assertSame(10.0, $this->stockOf($productSaleElements));
    }

    public function testAStockActionTakesOutTheStockOfAnOrderThatDidNotTakeItWhenPlaced(): void
    {
        $this->stockIsManagedOnOrderCreation(false);
        $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_PAID, AdjustStockAction::OPERATION_DECREASE);
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf($productSaleElements, 3, OrderStatus::CODE_NOT_PAID);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertSame(7.0, $this->stockOf($productSaleElements));
    }

    public function testTheSimulationAnnouncesWhatTheStatusChangeThenDoes(): void
    {
        $leaveAlone = $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_REFUNDED, AdjustStockAction::OPERATION_NONE);
        $simulator = $this->getService(StockOperationSimulator::class);

        $canceled = $this->createProductSaleElements(10);
        $canceledOrder = $this->orderOf($canceled, 3, OrderStatus::CODE_PAID);
        $refunded = $this->createProductSaleElements(10);
        $refundedOrder = $this->orderOf($refunded, 3, OrderStatus::CODE_PAID);

        $toCanceled = $simulator->simulate($canceledOrder, $this->orderStatus(OrderStatus::CODE_CANCELED));
        $toRefunded = $simulator->simulate($refundedOrder, $this->orderStatus(OrderStatus::CODE_REFUNDED));

        self::assertSame(10.0, $this->stockOf($canceled), 'The simulation moves nothing.');
        self::assertSame(['increase', null], [$toCanceled->operationName(), $toCanceled->actionId]);
        self::assertSame(['none', $leaveAlone->getId()], [$toRefunded->operationName(), $toRefunded->actionId]);

        $this->moveOrderTo($canceledOrder, OrderStatus::CODE_CANCELED);
        $this->moveOrderTo($refundedOrder, OrderStatus::CODE_REFUNDED);

        self::assertSame(13.0, $this->stockOf($canceled));
        self::assertSame(10.0, $this->stockOf($refunded));
    }

    public function testTheSimulationRecordsNoFailureForARefusedPayload(): void
    {
        $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_CANCELED, 'drop_table');
        $order = $this->orderOf($this->createProductSaleElements(10), 3, OrderStatus::CODE_PAID);

        $decision = $this->getService(StockOperationSimulator::class)->simulate($order, $this->orderStatus(OrderStatus::CODE_CANCELED));

        self::assertSame(['increase', null], [$decision->operationName(), $decision->actionId]);
        self::assertSame(0, OrderStatusActionFailureQuery::create()->filterByOrderId($order->getId())->count());
    }

    public function testTheOrderHistoryTellsTheStockMovementOfAStatusChangeAndWhatChoseIt(): void
    {
        $leaveAlone = $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_REFUNDED, AdjustStockAction::OPERATION_NONE);
        $byAction = $this->orderOf($this->createProductSaleElements(10), 3, OrderStatus::CODE_PAID);
        $byCoreRule = $this->orderOf($this->createProductSaleElements(10), 3, OrderStatus::CODE_PAID);
        $withoutMovement = $this->orderOf($this->createProductSaleElements(10), 3, OrderStatus::CODE_PAID);

        $this->moveOrderTo($byAction, OrderStatus::CODE_REFUNDED);
        $this->moveOrderTo($byCoreRule, OrderStatus::CODE_CANCELED);
        $this->moveOrderTo($withoutMovement, OrderStatus::CODE_PROCESSING);

        self::assertSame(['stock' => 'none', 'stock_action_id' => $leaveAlone->getId()], $this->stockOfLastStatusLine($byAction));
        self::assertSame(['stock' => 'increase', 'stock_action_id' => null], $this->stockOfLastStatusLine($byCoreRule));
        self::assertSame([], $this->stockOfLastStatusLine($withoutMovement), 'Nothing moved and no action chose it: the line says nothing of the stock.');
    }

    public function testARefusedStatusChangeLeavesNoStatusLineInTheOrderHistory(): void
    {
        ConfigQuery::write('check_available_stock', '1');
        $this->stockIsManagedOnOrderCreation(false);
        $productSaleElements = $this->createProductSaleElements(1);
        $order = $this->orderOf($productSaleElements, 3, OrderStatus::CODE_NOT_PAID);

        try {
            $this->moveOrderTo($order, OrderStatus::CODE_PAID);
            self::fail('A stock the order cannot take must refuse the status change.');
        } catch (TheliaProcessException) {
        }

        self::assertSame(1.0, $this->stockOf($productSaleElements));
        self::assertSame(0, OrderHistoryQuery::create()->filterByOrderId($order->getId())->filterByEventType(OrderHistoryEventType::STATUS_CHANGED->value)->count());
    }

    public function testTheBackOfficeIsToldWhichStockActionsNeverApplyAndWhichModulesAreGone(): void
    {
        $first = $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_CANCELED, AdjustStockAction::OPERATION_NONE, 1);
        $overridden = $this->stockAction(OrderStatusActionTrigger::TRANSITION, OrderStatus::CODE_PAID, OrderStatus::CODE_CANCELED, AdjustStockAction::OPERATION_INCREASE, 2, [AdjustStockAction::FIELD_PAYMENT_MODULES => ['Cheque']]);
        $transitionFirst = $this->stockAction(OrderStatusActionTrigger::TRANSITION, OrderStatus::CODE_PAID, OrderStatus::CODE_REFUNDED, AdjustStockAction::OPERATION_NONE, 3);
        $enterAfter = $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_REFUNDED, AdjustStockAction::OPERATION_INCREASE, 4);
        $withAGoneModule = $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_SENT, AdjustStockAction::OPERATION_DECREASE, 5, [AdjustStockAction::FIELD_PAYMENT_MODULES => ['Cheque', 'NoSuchModule']]);
        $diagnostics = $this->getService(StockActionDiagnostics::class);

        $overriddenActions = $diagnostics->overriddenActions();

        self::assertSame($first->getId(), $overriddenActions[$overridden->getId()] ?? null);
        self::assertArrayNotHasKey($enterAfter->getId(), $overriddenActions, 'A transition placed first does not cover every entry into the status.');
        self::assertArrayNotHasKey($transitionFirst->getId(), $overriddenActions);
        self::assertSame(['NoSuchModule'], $diagnostics->missingModules($withAGoneModule));
        self::assertSame([], $diagnostics->missingModules($overridden));
    }

    public function testAnActionWhoseLimitNamesAGoneModuleOverridesNoActionAfterIt(): void
    {
        $ignored = $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_REFUNDED, AdjustStockAction::OPERATION_NONE, 1, [AdjustStockAction::FIELD_PAYMENT_MODULES => ['Cheque', 'NoSuchModule']]);
        $later = $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_REFUNDED, AdjustStockAction::OPERATION_INCREASE, 2, [AdjustStockAction::FIELD_PAYMENT_MODULES => ['Cheque']]);
        $diagnostics = $this->getService(StockActionDiagnostics::class);

        self::assertArrayNotHasKey($later->getId(), $diagnostics->overriddenActions(), 'The first action is ignored for its gone module: the later one applies to cheques.');
        self::assertSame(['NoSuchModule'], $diagnostics->missingModules($ignored));
    }

    public function testAnActionOfACanonicalStatusOverridesTheOnesOfACustomStatusEquivalentToIt(): void
    {
        $this->factory->orderStatus(['code' => 'canceled_by_customer', 'equivalentCode' => OrderStatus::CODE_CANCELED]);
        $this->factory->orderStatus(['code' => 'refunded_by_shop', 'equivalentCode' => OrderStatus::CODE_REFUNDED]);
        $canonical = $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_CANCELED, AdjustStockAction::OPERATION_NONE, 1);
        $custom = $this->stockAction(OrderStatusActionTrigger::ENTER, null, 'canceled_by_customer', AdjustStockAction::OPERATION_INCREASE, 2);
        $customFirst = $this->stockAction(OrderStatusActionTrigger::ENTER, null, 'refunded_by_shop', AdjustStockAction::OPERATION_NONE, 3);
        $canonicalAfter = $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_REFUNDED, AdjustStockAction::OPERATION_INCREASE, 4);

        $overriddenActions = $this->getService(StockActionDiagnostics::class)->overriddenActions();

        self::assertSame($canonical->getId(), $overriddenActions[$custom->getId()] ?? null, 'A change into the custom status reads the actions of the canonical one first.');
        self::assertArrayNotHasKey($canonicalAfter->getId(), $overriddenActions, 'A change into the canonical status does not read the actions of a custom one.');
        self::assertArrayNotHasKey($customFirst->getId(), $overriddenActions);
    }

    public function testARefusedPayloadIsRecordedOnceTheStatusChangeIsCommittedAndNotWhenTheChangeIsRefused(): void
    {
        $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_CANCELED, 'drop_table');
        $committed = $this->orderOf($this->createProductSaleElements(10), 3, OrderStatus::CODE_PAID);

        $this->moveOrderTo($committed, OrderStatus::CODE_CANCELED);

        self::assertSame(1, OrderStatusActionFailureQuery::create()->filterByOrderId($committed->getId())->count());

        ConfigQuery::write('check_available_stock', '1');
        $this->stockIsManagedOnOrderCreation(false);
        $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_PAID, 'drop_table', 2);
        $refused = $this->orderOf($this->createProductSaleElements(1), 3, OrderStatus::CODE_NOT_PAID);

        try {
            $this->moveOrderTo($refused, OrderStatus::CODE_PAID);
            self::fail('A stock the order cannot take must refuse the status change.');
        } catch (TheliaProcessException) {
        }

        self::assertSame(0, OrderStatusActionFailureQuery::create()->filterByOrderId($refused->getId())->count());
    }

    public function testTheDecisionOfAFailedStatusChangeIsNotHandedToAnotherObjectOfTheSameOrder(): void
    {
        ConfigQuery::write('check_available_stock', '1');
        $this->stockIsManagedOnOrderCreation(false);
        $order = $this->orderOf($this->createProductSaleElements(1), 3, OrderStatus::CODE_NOT_PAID);

        try {
            $this->moveOrderTo($order, OrderStatus::CODE_PAID);
            self::fail('A stock the order cannot take must refuse the status change.');
        } catch (TheliaProcessException) {
        }

        $listener = $this->getService(StockOperationActionListener::class);
        $sameOrderReloaded = new Order();
        $sameOrderReloaded->setId($order->getId());

        self::assertNull($listener->takeDecisionFor($sameOrderReloaded));
        self::assertNotNull($listener->takeDecisionFor($order), 'The failed change of this very object is what is left, until the next decision replaces it.');
    }

    public function testTheHistoryLineSaysWhatAModuleListenerDecidedAfterTheStockActions(): void
    {
        $this->stockAction(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_CANCELED, AdjustStockAction::OPERATION_INCREASE);
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf($productSaleElements, 3, OrderStatus::CODE_PAID);
        $moduleListener = static function (GetStockUpdateOperationOnOrderStatusChangeEvent $event): void {
            $event->setOperation($event::DECREASE_STOCK)->setDecidingActionId(null);
        };
        $this->dispatcher->addListener(TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE, $moduleListener, 0);

        try {
            $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE, $moduleListener);
        }

        self::assertSame(7.0, $this->stockOf($productSaleElements));
        self::assertSame(['stock' => 'decrease', 'stock_action_id' => null], $this->stockOfLastStatusLine($order));
    }

    private function stockIsManagedOnOrderCreation(bool $managed): void
    {
        $listener = static function (ManageStockOnCreationEvent $event) use ($managed): void {
            $event->setManageStock($managed);
        };
        $eventName = TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque');
        $this->dispatcher->addListener($eventName, $listener, -1024);
        $this->stockOnCreationListener = [$eventName, $listener];
    }

    /**
     * @param array<string, mixed> $limits
     */
    private function stockAction(
        OrderStatusActionTrigger $trigger,
        ?string $fromCode,
        string $toCode,
        string $operation,
        int $position = 1,
        array $limits = [],
    ): OrderStatusAction {
        $action = (new OrderStatusAction())
            ->setTriggerType($trigger->value)
            ->setFromStatusId(null === $fromCode ? null : $this->orderStatus($fromCode)->getId())
            ->setToStatusId($this->orderStatus($toCode)->getId())
            ->setActionType(AdjustStockAction::getType())
            ->setDecodedPayload([AdjustStockAction::FIELD_OPERATION => $operation] + $limits)
            ->setPosition($position)
            ->setActive(true);
        $action->save();

        return $action;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function orderOf(ProductSaleElements $productSaleElements, int $quantity, string $statusCode, array $overrides = []): Order
    {
        $order = $this->factory->order(null, ['statusCode' => $statusCode] + $overrides);

        (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef('ref-'.$productSaleElements->getId())
            ->setProductSaleElementsRef((string) $productSaleElements->getRef())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setTitle('Product')
            ->setQuantity((float) $quantity)
            ->setPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save();

        return $order;
    }

    private function createProductSaleElements(int $quantity): ProductSaleElements
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());

        return $this->factory->productSaleElement($product, ['quantity' => $quantity]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stockOfLastStatusLine(Order $order): array
    {
        $line = OrderHistoryQuery::create()
            ->filterByOrderId($order->getId())
            ->filterByEventType(OrderHistoryEventType::STATUS_CHANGED->value)
            ->orderById(Criteria::DESC)
            ->findOne();
        self::assertNotNull($line);

        return array_intersect_key($line->getDecodedPayload(), ['stock' => true, 'stock_action_id' => true]);
    }

    private function stockOf(ProductSaleElements $productSaleElements): float
    {
        return (float) ProductSaleElementsQuery::create()->findPk($productSaleElements->getId())->getQuantity();
    }
}
