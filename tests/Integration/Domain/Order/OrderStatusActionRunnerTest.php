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

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Invoice\InvoiceRefAllocator;
use Thelia\Domain\Order\Enum\OrderStatusActionTrigger;
use Thelia\Domain\Order\Service\OrderStatusCatalog;
use Thelia\Domain\Order\StatusAction\Effect\AdjustStockAction;
use Thelia\Domain\Order\StatusAction\Effect\AllocateInvoiceRefAction;
use Thelia\Domain\Order\StatusAction\Effect\ReleaseCouponsAction;
use Thelia\Domain\Order\StatusAction\Effect\SendCustomerEmailAction;
use Thelia\Domain\Order\StatusAction\Effect\SendShopManagersEmailAction;
use Thelia\Domain\Order\StatusAction\OrderStatusActionInterface;
use Thelia\Domain\Order\StatusAction\OrderStatusActionRegistry;
use Thelia\Domain\Order\StatusAction\OrderStatusActionRunner;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\Customer;
use Thelia\Model\LangQuery;
use Thelia\Model\Message;
use Thelia\Model\Order;
use Thelia\Model\OrderCouponQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusAction;
use Thelia\Model\OrderStatusActionFailureQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\RecordingMailerFactory;
use Thelia\Tests\Support\Order\ExplodingAction;
use Thelia\Tests\Support\Order\LeakingMailerFactory;
use Thelia\Tests\Support\Order\MovesOrders;

final class OrderStatusActionRunnerTest extends ActionIntegrationTestCase
{
    use MovesOrders;

    protected function setUp(): void
    {
        parent::setUp();
        // The automatic numbering listener stays out of the way: what is asserted
        // here is what the configured actions do by themselves.
        ConfigQuery::write(InvoiceRefAllocator::CONFIG_ENABLED, '0');
    }

    protected function tearDown(): void
    {
        // The transaction rollback puts the config rows back, never the static cache
        // ConfigQuery::write() fills: without this, the values written here are read
        // by every later test of the process.
        ConfigQuery::resetCache();

        parent::tearDown();
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

    public function testAStockActionOnATransitionTakesTheOrderedQuantityOutOfStock(): void
    {
        $this->action(
            OrderStatusActionTrigger::TRANSITION,
            OrderStatus::CODE_PROCESSING,
            OrderStatus::CODE_SENT,
            AdjustStockAction::getType(),
            [AdjustStockAction::FIELD_OPERATION => AdjustStockAction::OPERATION_DECREASE],
        );
        $productSaleElements = $this->createProductSaleElements(10);
        // Neither status is a paid one, so the core listener leaves the stock alone:
        // what the quantity ends up being is the work of the action and of it alone.
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PROCESSING]);
        $this->addOrderProduct($order, $productSaleElements, 4);

        $this->moveOrderTo($order, OrderStatus::CODE_SENT);

        self::assertSame(6.0, $this->stockOf($productSaleElements));
        self::assertSame(0, OrderStatusActionFailureQuery::create()->filterByOrderId($order->getId())->count());
    }

    /**
     * The effects are found by their tag, never by a switch: a shop that installs a
     * module gets its action type without anyone editing the core.
     */
    public function testTheInstalledActionTypesAreDiscoveredByTheirTag(): void
    {
        $registry = $this->getService(OrderStatusActionRegistry::class);

        foreach ([
            SendCustomerEmailAction::class,
            SendShopManagersEmailAction::class,
            AdjustStockAction::class,
            AllocateInvoiceRefAction::class,
            ReleaseCouponsAction::class,
        ] as $effect) {
            self::assertInstanceOf($effect, $registry->get($effect::getType()), \sprintf('%s must be discovered through its tag.', $effect));
        }
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
        $orderCoupon = $this->factory->orderCoupon($order, $coupon);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);
        self::assertSame(0, CouponQuery::create()->findPk($coupon->getId())->getMaxUsage(), 'Paying the order consumed the usage.');

        $this->moveOrderTo($order, OrderStatus::CODE_PROCESSING);

        self::assertSame(1, CouponQuery::create()->findPk($coupon->getId())->getMaxUsage());
        self::assertTrue((bool) OrderCouponQuery::create()->findPk($orderCoupon->getId())->getUsageCanceled());
    }

    public function testTheCustomerEmailActionSendsTheChosenMessageWithTheOrderParameters(): void
    {
        $mailer = $this->recordingMailer();
        $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_SENT, SendCustomerEmailAction::getType(), [SendCustomerEmailAction::FIELD_MESSAGE_CODE => 'order_confirmation']);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PROCESSING]);

        $this->runnerWith(new SendCustomerEmailAction($mailer))
            ->onOrderStatusUpdate($this->statusChange($order, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT));

        $sent = $mailer->parametersOfMessagesSent('order_confirmation');
        self::assertCount(1, $sent);
        self::assertSame($order->getRef(), $sent[0]['order_ref']);
        self::assertSame(OrderStatus::CODE_SENT, $sent[0]['order_status_code']);
        self::assertSame(OrderStatus::CODE_PROCESSING, $sent[0]['previous_order_status_code']);
        self::assertSame($order->getCustomerId(), $sent[0]['customer_id']);
    }

    /**
     * Recipe 3 of #160: the customer reads the message in their own language, whatever
     * the language of the shop or of the administrator who moved the order.
     */
    public function testTheCustomerEmailIsSentInTheLanguageOfTheCustomer(): void
    {
        $mailer = $this->recordingMailer();
        $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_SENT, SendCustomerEmailAction::getType(), [SendCustomerEmailAction::FIELD_MESSAGE_CODE => 'order_confirmation']);
        $french = $this->factory->order($this->customerSpeaking('fr_FR'), ['statusCode' => OrderStatus::CODE_PROCESSING]);
        $english = $this->factory->order($this->customerSpeaking('en_US'), ['statusCode' => OrderStatus::CODE_PROCESSING]);

        $runner = $this->runnerWith(new SendCustomerEmailAction($mailer));
        $runner->onOrderStatusUpdate($this->statusChange($french, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT));
        $runner->onOrderStatusUpdate($this->statusChange($english, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT));

        self::assertSame(['fr_FR', 'en_US'], array_column($mailer->messages, 'locale'));
    }

    public function testTheShopManagersEmailActionSendsTheChosenMessageToTheConfiguredRecipients(): void
    {
        ConfigQuery::write('store_notification_emails', 'manager@example.com,second-manager@example.com');
        $mailer = $this->recordingMailer();
        $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_SENT, SendShopManagersEmailAction::getType(), [SendShopManagersEmailAction::FIELD_MESSAGE_CODE => 'order_confirmation']);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PROCESSING]);

        $this->runnerWith(new SendShopManagersEmailAction($mailer))
            ->onOrderStatusUpdate($this->statusChange($order, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT));

        self::assertCount(1, $mailer->messages);
        self::assertSame(
            ['manager@example.com', 'second-manager@example.com'],
            array_keys($mailer->messages[0]['to']),
            'The managers of the shop are the recipients the configuration names.',
        );
        $sent = $mailer->parametersOfMessagesSent('order_confirmation');
        self::assertSame($order->getRef(), $sent[0]['order_ref']);
        self::assertSame(OrderStatus::CODE_SENT, $sent[0]['order_status_code']);
    }

    /**
     * An e-mail that never leaves is exactly the case #160 asks to journal: the
     * merchant has to learn that the customer was not told, while the order keeps
     * moving and the rest of the configuration still runs.
     */
    public function testAnEmailThatCannotBeSentLeavesTheStatusChangedIsRecordedAndDoesNotStopTheNextAction(): void
    {
        ConfigQuery::write('store_email', 'shop@example.com');
        $messageCode = $this->messageWithoutABody();
        $emailAction = $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_PROCESSING, SendCustomerEmailAction::getType(), [SendCustomerEmailAction::FIELD_MESSAGE_CODE => $messageCode], 1);
        $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_PROCESSING, AllocateInvoiceRefAction::getType(), [], 2);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);

        $this->moveOrderTo($order, OrderStatus::CODE_PROCESSING);

        $reloaded = $this->reload($order);
        self::assertSame(OrderStatus::CODE_PROCESSING, $reloaded->getOrderStatus()->getCode(), 'A mail that could not be sent must not undo the status change.');
        self::assertNotEmpty($reloaded->getInvoiceRef(), 'The action after the failing e-mail still ran.');

        $failure = OrderStatusActionFailureQuery::create()->filterByActionId($emailAction->getId())->findOne();
        self::assertNotNull($failure, 'An e-mail that never left must be journalled.');
        self::assertStringContainsString($messageCode, $failure->getMessage());
        self::assertStringNotContainsString((string) $order->getCustomer()->getEmail(), $failure->getMessage());
    }

    /**
     * A mail transport names the recipient and carries its own credentials when it
     * fails. Neither belongs in a journal an administrator reads (#161, security).
     */
    public function testAFailedEmailIsJournalledWithoutTheCustomerAddressNorTheTransportDetail(): void
    {
        ConfigQuery::write('store_email', 'shop@example.com');
        $messageCode = $this->renderableMessage();
        $action = $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_SENT, SendCustomerEmailAction::getType(), [SendCustomerEmailAction::FIELD_MESSAGE_CODE => $messageCode]);
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PROCESSING]);
        $customerEmail = (string) $order->getCustomer()->getEmail();

        $this->runnerWith(new SendCustomerEmailAction($this->leakingMailer()))
            ->onOrderStatusUpdate($this->statusChange($order, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT));

        $failure = OrderStatusActionFailureQuery::create()->filterByActionId($action->getId())->findOne();
        self::assertNotNull($failure, 'A transport failure must be journalled.');
        self::assertStringNotContainsString($customerEmail, $failure->getMessage());
        self::assertStringNotContainsString(LeakingMailerFactory::LEAKED_SECRET, $failure->getMessage());
        // What is left has to be enough for an administrator to act on: which message
        // did not leave, and what refused it.
        self::assertStringContainsString($messageCode, $failure->getMessage());
        self::assertStringContainsString(TransportException::class, $failure->getMessage());
    }

    public function testAnUnexpectedExceptionIsRecordedWithoutItsRawMessage(): void
    {
        $action = $this->action(OrderStatusActionTrigger::ENTER, null, OrderStatus::CODE_SENT, ExplodingAction::getType());
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PROCESSING]);

        $this->runnerWith(new ExplodingAction())
            ->onOrderStatusUpdate($this->statusChange($order, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT));

        $failure = OrderStatusActionFailureQuery::create()->filterByActionId($action->getId())->findOne();
        self::assertNotNull($failure);
        self::assertStringNotContainsString(ExplodingAction::LEAKED_DSN, $failure->getMessage());
        self::assertStringContainsString(\RuntimeException::class, $failure->getMessage());
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

    /**
     * A runner wired to the given actions only, for the cases a test has to drive
     * the effect itself instead of letting the container's services run.
     */
    private function runnerWith(OrderStatusActionInterface ...$actions): OrderStatusActionRunner
    {
        return new OrderStatusActionRunner(
            new OrderStatusActionRegistry($actions),
            $this->getService(OrderStatusCatalog::class),
        );
    }

    private function statusChange(Order $order, string $fromCode, string $toCode): OrderEvent
    {
        $event = new OrderEvent($order);
        $event->setPreviousStatusId($this->orderStatus($fromCode)->getId());
        $event->setStatus($this->orderStatus($toCode)->getId());

        return $event;
    }

    private function customerSpeaking(string $locale): Customer
    {
        $lang = LangQuery::create()->findOneByLocale($locale);
        self::assertNotNull($lang, "Seeded language '$locale' is missing.");

        $customer = $this->factory->customer($this->factory->customerTitle());
        $customer->setLangId($lang->getId())->save();

        return $customer;
    }

    private function recordingMailer(): RecordingMailerFactory
    {
        return new RecordingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
        );
    }

    private function leakingMailer(): LeakingMailerFactory
    {
        return new LeakingMailerFactory(
            $this->getService(TemplateHelperInterface::class),
            $this->getService(ParserResolver::class),
            $this->getService(MailerInterface::class),
        );
    }

    /**
     * A message the mailer cannot build: no body at all, which is how a shop breaks
     * a template in practice.
     */
    private function messageWithoutABody(): string
    {
        $message = new Message();
        $message->setName('order_status_action_unsendable_'.uniqid());
        $message->setLocale('en_US');
        $message->setSubject('Subject');
        $message->save();

        return (string) $message->getName();
    }

    private function renderableMessage(): string
    {
        $message = new Message();
        $message->setName('order_status_action_renderable_'.uniqid());
        $message->setLocale('en_US');
        $message->setSubject('Subject');
        $message->setTextMessage('Your order changed status.');
        $message->save();

        return (string) $message->getName();
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

    private function reload(Order $order): Order
    {
        $reloaded = OrderQuery::create()->findPk($order->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }
}
