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

use Symfony\Component\Mailer\MailerInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Invoice\InvoiceRefAllocator;
use Thelia\Domain\Order\Enum\OrderStatusActionTrigger;
use Thelia\Domain\Order\Service\OrderStatusCatalog;
use Thelia\Domain\Order\StatusAction\Effect\AdjustStockAction;
use Thelia\Domain\Order\StatusAction\Effect\AllocateInvoiceRefAction;
use Thelia\Domain\Order\StatusAction\Effect\ReleaseCouponsAction;
use Thelia\Domain\Order\StatusAction\Effect\SendCustomerEmailAction;
use Thelia\Domain\Order\StatusAction\OrderStatusActionRegistry;
use Thelia\Domain\Order\StatusAction\OrderStatusActionRunner;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderCoupon;
use Thelia\Model\OrderCouponQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusAction;
use Thelia\Model\OrderStatusActionFailureQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\RecordingMailerFactory;

final class OrderStatusActionRunnerTest extends ActionIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The automatic numbering listener stays out of the way: what is asserted
        // here is what the configured actions do by themselves.
        ConfigQuery::write(InvoiceRefAllocator::CONFIG_ENABLED, '0');
    }

    public function testAnActionOnEnteringAStatusRunsAfterTheStatusIsPersisted(): void
    {
        $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_PROCESSING, AllocateInvoiceRefAction::getType());
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);

        $this->moveOrderTo($order, OrderStatus::CODE_PROCESSING);

        $reloaded = $this->reload($order);
        self::assertSame(OrderStatus::CODE_PROCESSING, $reloaded->getOrderStatus()->getCode());
        self::assertNotEmpty($reloaded->getInvoiceRef(), 'The action numbered the invoice on entering "processing".');
    }

    public function testAnActionOnAPreciseTransitionOnlyFiresForThatTransition(): void
    {
        $this->action(OrderStatusActionTrigger::TRANSITION, OrderStatus::CODE_PAID, OrderStatus::CODE_PROCESSING, AllocateInvoiceRefAction::getType());

        $fromSent = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $this->moveOrderTo($fromSent, OrderStatus::CODE_PROCESSING);
        self::assertEmpty($this->reload($fromSent)->getInvoiceRef(), 'sent -> processing is not the configured transition.');

        $fromPaid = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $this->moveOrderTo($fromPaid, OrderStatus::CODE_PROCESSING);
        self::assertNotEmpty($this->reload($fromPaid)->getInvoiceRef());
    }

    public function testAStockActionOnATransitionPutsTheOrderedQuantityBackInStock(): void
    {
        $this->action(
            OrderStatusActionTrigger::TRANSITION,
            OrderStatus::CODE_SENT,
            OrderStatus::CODE_PROCESSING,
            AdjustStockAction::getType(),
            [AdjustStockAction::FIELD_OPERATION => AdjustStockAction::OPERATION_INCREASE],
        );
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_SENT]);
        $this->addOrderProduct($order, $productSaleElements, 3);

        $this->moveOrderTo($order, OrderStatus::CODE_PROCESSING);

        self::assertSame(13.0, $this->stockOf($productSaleElements));
    }

    public function testAFailingActionLeavesTheStatusChangedIsRecordedAndDoesNotStopTheNextOnes(): void
    {
        $broken = $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_PROCESSING, 'action_from_a_missing_module', [], 1);
        $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_PROCESSING, AllocateInvoiceRefAction::getType(), [], 2);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);

        $this->moveOrderTo($order, OrderStatus::CODE_PROCESSING);

        $reloaded = $this->reload($order);
        self::assertSame(OrderStatus::CODE_PROCESSING, $reloaded->getOrderStatus()->getCode());
        self::assertNotEmpty($reloaded->getInvoiceRef(), 'The action after the failing one still ran.');

        $failures = OrderStatusActionFailureQuery::create()->filterByOrderId($order->getId())->find();
        self::assertCount(1, $failures);
        self::assertSame($broken->getId(), $failures->getFirst()->getActionId());
        self::assertStringContainsString('Unknown action type', $failures->getFirst()->getMessage());
    }

    public function testAnInvalidPayloadIsRefusedAndRecordedInsteadOfBeingRun(): void
    {
        $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_PROCESSING, AdjustStockAction::getType(), [AdjustStockAction::FIELD_OPERATION => 'drop_table']);
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $this->addOrderProduct($order, $productSaleElements, 3);

        $this->moveOrderTo($order, OrderStatus::CODE_PROCESSING);

        self::assertSame(10.0, $this->stockOf($productSaleElements));
        $failure = OrderStatusActionFailureQuery::create()->filterByOrderId($order->getId())->findOne();
        self::assertNotNull($failure);
        self::assertStringContainsString('"operation"', $failure->getMessage());
    }

    public function testAnInactiveActionDoesNotRun(): void
    {
        $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_PROCESSING, AllocateInvoiceRefAction::getType(), [], 1, false);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);

        $this->moveOrderTo($order, OrderStatus::CODE_PROCESSING);

        self::assertEmpty($this->reload($order)->getInvoiceRef());
    }

    public function testActionsRunInPositionOrder(): void
    {
        // The stock ends at 10 - 3 = 7 only if the increase runs before the decrease:
        // with availability checked, decreasing 3 from 0 would be refused.
        $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_PROCESSING, AdjustStockAction::getType(), [AdjustStockAction::FIELD_OPERATION => AdjustStockAction::OPERATION_DECREASE], 2);
        $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_PROCESSING, AdjustStockAction::getType(), [AdjustStockAction::FIELD_OPERATION => AdjustStockAction::OPERATION_INCREASE], 1);
        $productSaleElements = $this->createProductSaleElements(0);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);
        $this->addOrderProduct($order, $productSaleElements, 3);

        $this->moveOrderTo($order, OrderStatus::CODE_PROCESSING);

        self::assertSame(0.0, $this->stockOf($productSaleElements));
        self::assertSame(0, OrderStatusActionFailureQuery::create()->filterByOrderId($order->getId())->count());
    }

    public function testACustomStatusEquivalentToPaidFiresTheActionsConfiguredOnPaid(): void
    {
        $this->factory->orderStatus(['code' => 'paid_on_delivery', 'equivalentCode' => OrderStatus::CODE_PAID]);
        $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_PAID, AllocateInvoiceRefAction::getType());
        $order = $this->factory->order();

        $this->moveOrderTo($order, 'paid_on_delivery');

        self::assertNotEmpty($this->reload($order)->getInvoiceRef());
    }

    public function testTheCouponReleaseActionGivesTheUsageBackToTheCoupon(): void
    {
        $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_PROCESSING, ReleaseCouponsAction::getType());
        $coupon = $this->factory->coupon(['code' => 'RELEASE-ME', 'maxUsage' => 1]);
        $order = $this->factory->order();
        $orderCoupon = $this->rememberCouponOnOrder($order, $coupon);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);
        self::assertSame(0, CouponQuery::create()->findPk($coupon->getId())->getMaxUsage(), 'Paying the order consumed the usage.');

        $this->moveOrderTo($order, OrderStatus::CODE_PROCESSING);

        self::assertSame(1, CouponQuery::create()->findPk($coupon->getId())->getMaxUsage());
        self::assertTrue((bool) OrderCouponQuery::create()->findPk($orderCoupon->getId())->getUsageCanceled());
    }

    public function testTheCustomerEmailActionSendsTheChosenMessageWithTheOrderParameters(): void
    {
        $mailer = new RecordingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
        );
        $runner = new OrderStatusActionRunner(
            new OrderStatusActionRegistry([new SendCustomerEmailAction($mailer)]),
            $this->getService(OrderStatusCatalog::class),
        );
        $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_SENT, SendCustomerEmailAction::getType(), [SendCustomerEmailAction::FIELD_MESSAGE_CODE => 'order_confirmation']);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PROCESSING]);

        $event = new OrderEvent($order);
        $event->setPreviousStatusId($this->orderStatus(OrderStatus::CODE_PROCESSING)->getId());
        $event->setStatus($this->orderStatus(OrderStatus::CODE_SENT)->getId());
        $runner->onOrderStatusUpdate($event);

        $sent = $mailer->parametersOfMessagesSent('order_confirmation');
        self::assertCount(1, $sent);
        self::assertSame($order->getRef(), $sent[0]['order_ref']);
        self::assertSame(OrderStatus::CODE_SENT, $sent[0]['order_status_code']);
        self::assertSame(OrderStatus::CODE_PROCESSING, $sent[0]['previous_order_status_code']);
        self::assertSame($order->getCustomerId(), $mailer->customerMessages[0]['customer']->getId());
    }

    public function testTheCustomerEmailActionRefusesAMessageCodeThatDoesNotExist(): void
    {
        $action = $this->getService(SendCustomerEmailAction::class);

        $this->expectExceptionMessage('names no message');
        $action->normalizePayload([SendCustomerEmailAction::FIELD_MESSAGE_CODE => 'no_such_message']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function action(
        OrderStatusActionTrigger $trigger,
        ?string $fromCode,
        string $toCode,
        string $type,
        array $payload = [],
        int $position = 1,
        bool $active = true,
    ): OrderStatusAction {
        $action = (new OrderStatusAction())
            ->setTriggerType($trigger->value)
            ->setFromStatusId(null === $fromCode ? null : $this->orderStatus($fromCode)->getId())
            ->setToStatusId($this->orderStatus($toCode)->getId())
            ->setActionType($type)
            ->setDecodedPayload($payload)
            ->setPosition($position)
            ->setActive($active);
        $action->save();

        return $action;
    }

    private function createProductSaleElements(int $quantity): ProductSaleElements
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());

        return $this->factory->productSaleElement($product, ['quantity' => $quantity]);
    }

    private function addOrderProduct(Order $order, ProductSaleElements $productSaleElements, int $quantity): void
    {
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
    }

    private function stockOf(ProductSaleElements $productSaleElements): float
    {
        return (float) ProductSaleElementsQuery::create()->findPk($productSaleElements->getId())->getQuantity();
    }

    private function rememberCouponOnOrder(Order $order, Coupon $coupon): OrderCoupon
    {
        $orderCoupon = (new OrderCoupon())
            ->setOrder($order)
            ->setUsageCanceled(1)
            ->setCode($coupon->getCode())
            ->setType($coupon->getType())
            ->setAmount('5')
            ->setTitle($coupon->getTitle())
            ->setShortDescription($coupon->getShortDescription())
            ->setDescription($coupon->getDescription())
            ->setStartDate($coupon->getStartDate())
            ->setExpirationDate($coupon->getExpirationDate())
            ->setIsCumulative($coupon->getIsCumulative())
            ->setIsRemovingPostage($coupon->getIsRemovingPostage())
            ->setIsAvailableOnSpecialOffers($coupon->getIsAvailableOnSpecialOffers())
            ->setSerializedConditions($coupon->getSerializedConditions())
            ->setPerCustomerUsageCount($coupon->getPerCustomerUsageCount());
        $orderCoupon->save();

        return $orderCoupon;
    }

    private function moveOrderTo(Order $order, string $statusCode): void
    {
        $event = new OrderEvent($order);
        $event->setStatus($this->orderStatus($statusCode)->getId());

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    private function orderStatus(string $code): OrderStatus
    {
        $status = OrderStatusQuery::create()->findOneByCode($code);
        self::assertNotNull($status, "Order status '$code' is missing.");

        return $status;
    }

    private function reload(Order $order): Order
    {
        $reloaded = OrderQuery::create()->findPk($order->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }
}
