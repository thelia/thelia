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

namespace Thelia\Tests\Integration\Domain\Order\Reminder;

use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Reminder\UnpaidOrderPaymentLink;
use Thelia\Domain\Order\Reminder\UnpaidOrderReminderOutcome;
use Thelia\Domain\Order\Reminder\UnpaidOrderReminderRunner;
use Thelia\Domain\Order\Reminder\UnpaidOrderReminderSettings;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The schedule applied to the unpaid orders, one order and one step at a time: a step is
 * sent once, only while the order is in it, never to an order paid or paid with an
 * excluded module, and the cancellation goes through the status change any cancellation
 * goes through.
 */
final class UnpaidOrderReminderRunnerTest extends ActionIntegrationTestCase
{
    private const MESSAGE = 'test_payment_reminder';

    private UnpaidOrderReminderRunner $runner;

    private \DateTimeImmutable $now;

    /** @var list<Email> */
    private array $sent = [];

    private \Closure $mailListener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = $this->getService(UnpaidOrderReminderRunner::class);
        $this->now = new \DateTimeImmutable();
        $this->registerTheMessage();
        ConfigQuery::write('store_email', 'shop@example.com');
        $this->mailListener = function (MessageEvent $event): void {
            $message = $event->getMessage();

            if ($message instanceof Email && !$event->isQueued()) {
                $this->sent[] = $message;
            }
        };
        $this->dispatcher->addListener(MessageEvent::class, $this->mailListener);
    }

    protected function tearDown(): void
    {
        $this->dispatcher->removeListener(MessageEvent::class, $this->mailListener);
        parent::tearDown();
        // The settings written here are rolled back with the rest: the cache follows.
        ConfigQuery::resetCache();
    }

    public function testWithoutAScheduleNothingHappens(): void
    {
        $this->schedule('');
        $order = $this->unpaidOrderAged(24 * 30);

        $report = $this->runner->run($this->now, 100, false);

        self::assertSame([], $report->outcomes());
        self::assertSame([], $this->sent);
        self::assertSame(OrderStatus::CODE_NOT_PAID, $this->statusOf($order));
    }

    public function testAReminderIsSentOnceAndWrittenToTheHistory(): void
    {
        $this->schedule('24:'.self::MESSAGE);
        $order = $this->unpaidOrderAged(25);

        $this->runner->run($this->now, 100, false);
        $this->runner->run($this->now, 100, false);

        self::assertCount(1, $this->mailsTo($order));
        $entries = OrderHistoryQuery::create()->filterByOrderId($order->getId())->filterByEventType(OrderHistoryEventType::PAYMENT_REMINDER_SENT->value)->find();
        self::assertCount(1, $entries);
        self::assertSame(['step' => 24, 'message' => self::MESSAGE], json_decode((string) $entries[0]->getPayload(), true));
    }

    public function testTheLinkOfTheMailOpensTheOrder(): void
    {
        if (null === $this->getService(RouterInterface::class)->getRouteCollection()->get(UnpaidOrderReminderRunner::PAYMENT_ROUTE)) {
            self::markTestSkipped('The front theme does not carry the route the reminder links to yet.');
        }

        $this->schedule('24:'.self::MESSAGE);
        $order = $this->unpaidOrderAged(25);

        $this->runner->run($this->now, 100, false);

        self::assertSame(1, preg_match('#LINK\[(?:[a-z]+://[^/\]]+)?/[^\]]*?([0-9]+\.[0-9]+\.[0-9a-f]+)\]#', (string) $this->mailsTo($order)[0]->getTextBody(), $matches));
        self::assertSame((int) $order->getId(), (int) $this->getService(UnpaidOrderPaymentLink::class)->findOrderForToken($matches[1])?->getId());
    }

    public function testAnOrderPaidOrNotYetDueGetsNothing(): void
    {
        $this->schedule('24:'.self::MESSAGE);
        $paid = $this->orderAged(25, OrderStatus::CODE_PAID);
        $young = $this->unpaidOrderAged(23);

        $this->runner->run($this->now, 100, false);

        self::assertSame([], $this->mailsTo($paid));
        self::assertSame([], $this->mailsTo($young));
    }

    public function testAnOrderGetsOnlyTheStepItIsIn(): void
    {
        // A schedule switched on in a shop holding older unpaid orders: none of them is
        // sent the reminders it is past.
        $this->schedule('24:'.self::MESSAGE.',72:'.self::MESSAGE.'');
        $order = $this->unpaidOrderAged(100);

        $this->runner->run($this->now, 100, false);
        $this->runner->run($this->now, 100, false);

        self::assertCount(1, $this->mailsTo($order));
        $payloads = array_map(static fn ($entry): array => json_decode((string) $entry->getPayload(), true), iterator_to_array(OrderHistoryQuery::create()->filterByOrderId($order->getId())->filterByEventType(OrderHistoryEventType::PAYMENT_REMINDER_SENT->value)->find()));
        self::assertSame([['step' => 72, 'message' => self::MESSAGE]], array_values($payloads));
    }

    public function testAnOrderPastTheCancellationIsCancelledWithoutAReminder(): void
    {
        $this->schedule('24:'.self::MESSAGE.',168:cancel');
        $order = $this->unpaidOrderAged(24 * 8);

        $report = $this->runner->run($this->now, 100, false);

        self::assertSame(OrderStatus::CODE_CANCELED, $this->statusOf($order));
        self::assertSame([], $this->mailsTo($order));
        self::assertSame([[$order->getRef(), 168, UnpaidOrderReminderOutcome::ACTION_CANCEL, UnpaidOrderReminderOutcome::STATUS_DONE]], $this->summary($report->outcomes()));
    }

    public function testAnOrderPaidWithAnExcludedModuleIsLeftAlone(): void
    {
        $this->schedule('24:'.self::MESSAGE.',168:cancel', 'Cheque');
        $order = $this->unpaidOrderAged(24 * 8);

        $this->runner->run($this->now, 100, false);

        self::assertSame(OrderStatus::CODE_NOT_PAID, $this->statusOf($order));
    }

    public function testAStatusDeclaredEquivalentToNotPaidIsRemindedToo(): void
    {
        $status = $this->factory->orderStatus(['code' => 'awaiting_transfer_'.uniqid(), 'title' => 'Awaiting transfer']);
        $status->setEquivalentCode(OrderStatus::CODE_NOT_PAID)->save();
        $this->schedule('24:'.self::MESSAGE);
        $order = $this->orderAged(25, (string) $status->getCode());

        $this->runner->run($this->now, 100, false);

        self::assertCount(1, $this->mailsTo($order));
    }

    public function testADryRunSaysWhatItWouldDoAndDoesNothing(): void
    {
        $this->schedule('24:'.self::MESSAGE.',168:cancel');
        $reminded = $this->unpaidOrderAged(25);
        $cancelled = $this->unpaidOrderAged(24 * 8);

        $report = $this->runner->run($this->now, 100, true);

        self::assertSame([
            [$reminded->getRef(), 24, UnpaidOrderReminderOutcome::ACTION_REMIND, UnpaidOrderReminderOutcome::STATUS_PLANNED],
            [$cancelled->getRef(), 168, UnpaidOrderReminderOutcome::ACTION_CANCEL, UnpaidOrderReminderOutcome::STATUS_PLANNED],
        ], $this->summary($this->only([$reminded, $cancelled], $report->outcomes())));
        self::assertSame([], $this->sent);
        self::assertSame(OrderStatus::CODE_NOT_PAID, $this->statusOf($cancelled));
        self::assertSame(0, OrderHistoryQuery::create()->filterByOrderId($reminded->getId())->filterByEventType(OrderHistoryEventType::PAYMENT_REMINDER_SENT->value)->count());
    }

    public function testAMailThatCannotLeaveFailsAloneAndIsNotTriedAgain(): void
    {
        // Tried again on every run, a broken address would take a place in every batch
        // for good: the failure is written down, and the next step is the next chance.
        $this->schedule('24:'.self::MESSAGE);
        $broken = $this->unpaidOrderAged(26);
        $broken->getCustomer()->setEmail('not an address')->save();
        $fine = $this->unpaidOrderAged(25);

        $first = $this->runner->run($this->now, 100, false);
        $second = $this->runner->run($this->now, 100, false);

        self::assertSame(UnpaidOrderReminderOutcome::STATUS_FAILED, $this->only([$broken], $first->outcomes())[0]->status);
        self::assertTrue($first->hasFailures());
        self::assertSame([], $this->only([$broken], $second->outcomes()));
        self::assertCount(1, $this->mailsTo($fine));
        self::assertSame(0, OrderHistoryQuery::create()->filterByOrderId($broken->getId())->filterByEventType(OrderHistoryEventType::PAYMENT_REMINDER_SENT->value)->count());
        self::assertSame(1, OrderHistoryQuery::create()->filterByOrderId($broken->getId())->filterByEventType(OrderHistoryEventType::PAYMENT_REMINDER_FAILED->value)->count());
    }

    public function testAnOrderPaidWhileTheRunIsBusyIsLeftAlone(): void
    {
        // The run read both orders, then spends time on the first one's mail; the second
        // is paid meanwhile, behind the back of the object the run holds.
        $this->schedule('24:'.self::MESSAGE.',168:cancel');
        $reminded = $this->unpaidOrderAged(25);
        $paidMeanwhile = $this->unpaidOrderAged(24 * 8);
        $paidStatusId = (int) OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID)->getId();
        $payment = function (MessageEvent $event) use ($paidMeanwhile, $paidStatusId): void {
            $this->getPropelConnection()->exec(\sprintf('UPDATE `order` SET status_id = %d WHERE id = %d', $paidStatusId, (int) $paidMeanwhile->getId()));
        };
        $this->dispatcher->addListener(MessageEvent::class, $payment);

        try {
            $report = $this->runner->run($this->now, 100, false);
        } finally {
            $this->dispatcher->removeListener(MessageEvent::class, $payment);
        }

        self::assertCount(1, $this->mailsTo($reminded));
        OrderTableMap::clearInstancePool();
        self::assertSame(OrderStatus::CODE_PAID, $this->statusOf($paidMeanwhile));
        self::assertSame([], $this->only([$paidMeanwhile], $report->outcomes()));
    }

    public function testTheCancellationGivesTheStockBack(): void
    {
        $this->schedule('168:cancel');
        $this->dispatcher->addListener(
            TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'),
            $manageStock = static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(true),
        );

        try {
            $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency(), ['baseQuantity' => 10]);
            $productSaleElements = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->findOne();
            $order = $this->unpaidOrderAged(24 * 8);
            (new OrderProduct())
                ->setOrderId($order->getId())
                ->setProductRef($product->getRef())
                ->setProductSaleElementsRef($productSaleElements->getRef())
                ->setProductSaleElementsId($productSaleElements->getId())
                ->setTitle('Reserved line')
                ->setQuantity(3)
                ->setPrice('10.000000')
                ->setPromoPrice('10.000000')
                ->setWasNew(0)
                ->setWasInPromo(0)
                ->save();
            $productSaleElements->setQuantity($productSaleElements->getQuantity() - 3)->save();

            $this->runner->run($this->now, 1000, false);
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'), $manageStock);
        }

        self::assertSame(OrderStatus::CODE_CANCELED, $this->statusOf($order));
        self::assertEqualsWithDelta(10.0, (float) ProductSaleElementsQuery::create()->findPk($productSaleElements->getId())->getQuantity(), 0.0001);
    }

    public function testAStepWhoseMessageDoesNotExistWaitsForItInsteadOfFailing(): void
    {
        $this->schedule('24:no_such_message_'.self::MESSAGE);
        $order = $this->unpaidOrderAged(25);

        $report = $this->runner->run($this->now, 100, false);

        self::assertSame(['no_such_message_'.self::MESSAGE], $report->missingMessages());
        self::assertSame([], $this->only([$order], $report->outcomes()));
        self::assertSame(0, OrderHistoryQuery::create()->filterByOrderId($order->getId())->filterByEventType(OrderHistoryEventType::PAYMENT_REMINDER_FAILED->value)->count());
    }

    public function testAnAnonymizedCustomerIsNeverMailed(): void
    {
        $this->schedule('24:'.self::MESSAGE);
        $order = $this->unpaidOrderAged(25);
        $order->getCustomer()->setAnonymizedAt(new \DateTimeImmutable())->save();

        $first = $this->runner->run($this->now, 100, false);
        $second = $this->runner->run($this->now, 100, false);

        self::assertSame([], $this->mailsTo($order));
        self::assertSame(UnpaidOrderReminderOutcome::STATUS_FAILED, $this->only([$order], $first->outcomes())[0]->status);
        self::assertSame([], $this->only([$order], $second->outcomes()));
    }

    public function testAnOrderPastTheWindowOfTheLastReminderIsNotReadAnyMore(): void
    {
        // Without a cancellation, an unpaid order stays unpaid for good: the run stops
        // looking at it once its last reminder could no longer be paid from.
        $this->schedule('24:'.self::MESSAGE);
        $tooOld = $this->unpaidOrderAged(24 + UnpaidOrderReminderRunner::LAST_REMINDER_WINDOW_IN_HOURS + 2);
        $inTime = $this->unpaidOrderAged(100);

        $report = $this->runner->run($this->now, 1000, false);

        self::assertSame([], $this->only([$tooOld], $report->outcomes()));
        self::assertCount(1, $this->mailsTo($inTime));
    }

    public function testMoreThanAPageOfOrdersIsRemindedInOneRun(): void
    {
        $this->schedule('24:'.self::MESSAGE);
        $orders = [];

        for ($i = 0; $i < 105; ++$i) {
            $orders[] = $this->unpaidOrderAged(25);
        }

        $report = $this->runner->run($this->now, 10000, false);

        self::assertCount(105, $this->only($orders, $report->outcomes()));
    }

    public function testARunHandlesAtMostItsLimitAndTheNextOneGoesOn(): void
    {
        $this->schedule('24:'.self::MESSAGE);
        $orders = [$this->unpaidOrderAged(27), $this->unpaidOrderAged(26), $this->unpaidOrderAged(25)];
        $mine = static fn (array $outcomes): array => array_filter($outcomes, static fn (UnpaidOrderReminderOutcome $outcome): bool => \in_array($outcome->orderId, array_map(static fn (Order $order): int => (int) $order->getId(), $orders), true));

        // Older unpaid orders of the test database may come first: the limit is counted on
        // everything the run acts on.
        $handled = 0;

        for ($run = 0; $run < 50 && $handled < 3; ++$run) {
            $report = $this->runner->run($this->now, 2, false);
            self::assertLessThanOrEqual(2, \count($report->outcomes()));
            $handled += \count($mine($report->outcomes()));
        }

        self::assertSame(3, $handled);

        foreach ($orders as $order) {
            self::assertCount(1, $this->mailsTo($order));
        }
    }

    private function schedule(string $setting, string $excludedModules = ''): void
    {
        ConfigQuery::write(UnpaidOrderReminderSettings::SCHEDULE_KEY, $setting);
        ConfigQuery::write(UnpaidOrderReminderSettings::EXCLUDED_MODULES_KEY, $excludedModules);
    }

    private function unpaidOrderAged(int $hours): Order
    {
        return $this->orderAged($hours, OrderStatus::CODE_NOT_PAID);
    }

    private function orderAged(int $hours, string $statusCode): Order
    {
        $order = $this->factory->order(null, ['postage' => 20, 'statusCode' => $statusCode]);
        $order->setCreatedAt($this->now->modify(\sprintf('-%d hours -5 minutes', $hours)))->save();

        return $order;
    }

    private function statusOf(Order $order): string
    {
        return (string) OrderQuery::create()->findPk($order->getId())->getOrderStatus()->getCode();
    }

    /**
     * @return list<Email>
     */
    private function mailsTo(Order $order): array
    {
        $address = (string) $order->getCustomer()->getEmail();

        return array_values(array_filter($this->sent, static fn (Email $mail): bool => $address === $mail->getTo()[0]->getAddress()));
    }

    /**
     * @param list<Order>                      $orders
     * @param list<UnpaidOrderReminderOutcome> $outcomes
     *
     * @return list<UnpaidOrderReminderOutcome>
     */
    private function only(array $orders, array $outcomes): array
    {
        $ids = array_map(static fn (Order $order): int => (int) $order->getId(), $orders);

        return array_values(array_filter($outcomes, static fn (UnpaidOrderReminderOutcome $outcome): bool => \in_array($outcome->orderId, $ids, true)));
    }

    /**
     * @param list<UnpaidOrderReminderOutcome> $outcomes
     *
     * @return list<array{string, int, string, string}>
     */
    private function summary(array $outcomes): array
    {
        return array_map(static fn (UnpaidOrderReminderOutcome $outcome): array => [$outcome->orderRef, $outcome->delayInHours, $outcome->action, $outcome->status], $outcomes);
    }

    /**
     * A message of its own, its body in the database: the test does not depend on the
     * mail theme shipping the reminder template.
     */
    private function registerTheMessage(): void
    {
        if (null !== MessageQuery::create()->findOneByName(self::MESSAGE)) {
            return;
        }

        (new Message())
            ->setName(self::MESSAGE)
            ->setLocale('en_US')
            ->setTitle('Payment reminder')
            ->setSubject('Your order {{ order_ref }} waits for its payment')
            ->setTextMessage('Pay order {{ order_ref }}: LINK[{{ payment_url }}]')
            ->setHtmlMessage('<p>Pay order {{ order_ref }}: <a href="{{ payment_url }}">LINK[{{ payment_url }}]</a></p>')
            ->save();
    }
}
