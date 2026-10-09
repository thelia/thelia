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

namespace Thelia\Tests\Integration\Domain\Payment;

use Propel\Runtime\Propel;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentCaptureEvent;
use Thelia\Core\Event\Order\OrderPaymentSettlementEvent;
use Thelia\Core\Event\Order\OrderPaymentTransactionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\DTO\PaymentOperationResult;
use Thelia\Domain\Payment\Enum\PaymentTransactionState;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Domain\Payment\Exception\CaptureExceedsAuthorizationException;
use Thelia\Domain\Payment\Exception\ConflictingPaymentReferenceException;
use Thelia\Domain\Payment\Exception\DeferredCaptureNotSupportedException;
use Thelia\Domain\Payment\Exception\DuplicateCaptureException;
use Thelia\Domain\Payment\Exception\InvalidPaymentAmountException;
use Thelia\Domain\Payment\Exception\PaymentException;
use Thelia\Domain\Payment\Exception\PaymentProviderUnreachableException;
use Thelia\Domain\Payment\Exception\PaymentRefusedException;
use Thelia\Domain\Payment\Service\PaymentCaptureService;
use Thelia\Domain\Payment\Service\PaymentJournalLock;
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;
use Thelia\Domain\Payment\Service\PaymentTransactionTotalsReader;
use Thelia\Model\Map\OrderPaymentTransactionTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransaction;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Payment\DeferredCapturePaymentModule;

/**
 * The capture of a payment a module reserved first, as the back office and the admin
 * API trigger it, through ORDER_PAYMENT_CAPTURE.
 */
final class PaymentCaptureServiceTest extends ActionIntegrationTestCase
{
    private PaymentCaptureService $service;

    private PaymentTransactionRecorder $recorder;

    private PaymentTransactionTotalsReader $totals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(PaymentCaptureService::class);
        $this->recorder = $this->getService(PaymentTransactionRecorder::class);
        $this->totals = $this->getService(PaymentTransactionTotalsReader::class);
        $this->registerTheDeferredCaptureModule();
        DeferredCapturePaymentModule::reset();
    }

    protected function tearDown(): void
    {
        DeferredCapturePaymentModule::reset();
        parent::tearDown();
    }

    public function testCapturingThroughTheEventTakesTheWholeRemainderAndPaysTheOrder(): void
    {
        [$order, $authorization] = $this->authorizedOrder(120);

        $event = new OrderPaymentCaptureEvent($order);
        $this->dispatch($event, TheliaEvents::ORDER_PAYMENT_CAPTURE);

        $capture = $event->getTransaction();
        self::assertTrue($capture->isSucceeded());
        self::assertSame(PaymentTransactionType::CAPTURE->value, $capture->getType());
        self::assertSame('120.000000', $capture->getAmount());
        self::assertSame('CAP-'.$capture->getId(), $capture->getPspReference());
        self::assertSame($authorization->getId(), $capture->getParentId());
        self::assertSame(DeferredCapturePaymentModule::getModuleCode(), $capture->getActorLabel());

        self::assertSame([['order' => $order->getId(), 'amount' => 120.0, 'transaction' => $capture->getId()]], DeferredCapturePaymentModule::$captureCalls);
        self::assertSame('0.000000', $this->totals->forOrder($order->getId())->remainingToCapture);
        self::assertSame(OrderStatus::CODE_PAID, $this->statusCodeOf($order));
    }

    public function testAPartialCaptureLeavesTheRestAndTheOrderOnHold(): void
    {
        [$order] = $this->authorizedOrder(120);

        $this->service->capture($order, 50.0);

        $totals = $this->totals->forOrder($order->getId());
        self::assertSame('50.000000', $totals->captured);
        self::assertSame('70.000000', $totals->remainingToCapture);
        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, $this->statusCodeOf($order));
        self::assertCount(2, OrderPaymentTransactionQuery::create()->findJournal($order->getId()));

        $this->service->capture($order, 70.0);

        self::assertSame('0.000000', $this->totals->forOrder($order->getId())->remainingToCapture);
        self::assertSame(OrderStatus::CODE_PAID, $this->statusCodeOf($order));
    }

    public function testAnAmountAboveTheAuthorizationIsRefusedBeforeTheModuleIsCalled(): void
    {
        [$order] = $this->authorizedOrder(120);

        try {
            $this->service->capture($order, 120.01);
            self::fail('A capture above the authorization must be refused.');
        } catch (CaptureExceedsAuthorizationException) {
        }

        self::assertSame([], DeferredCapturePaymentModule::$captureCalls, 'The provider was not called.');
        self::assertCount(1, OrderPaymentTransactionQuery::create()->findJournal($order->getId()), 'No pending line was left behind.');
        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, $this->statusCodeOf($order));
    }

    public function testAZeroOrNegativeAmountIsRefused(): void
    {
        [$order] = $this->authorizedOrder(120);

        $this->expectException(InvalidPaymentAmountException::class);

        $this->service->capture($order, 0.0);
    }

    public function testAProviderRefusalLeavesAFailedLineWithItsMessageAndTheOrderUnpaid(): void
    {
        [$order] = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = PaymentOperationResult::failed('05', 'Do not honor', 'CAP-REFUSED');

        $capture = $this->service->capture($order);

        self::assertTrue($capture->isFailed());
        self::assertSame('05', $capture->getErrorCode());
        self::assertSame('Do not honor', $capture->getErrorMessage());
        self::assertSame('CAP-REFUSED', $capture->getPspReference());
        self::assertSame('120.000000', $this->totals->forOrder($order->getId())->remainingToCapture);
        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, $this->statusCodeOf($order));
        self::assertTrue($order->isNotPaid(false));
    }

    public function testAModuleThatThrowsLeavesTheLinePendingBecauseTheOutcomeIsUnknown(): void
    {
        // A call that timed out may have taken the money: the line stays pending and keeps
        // what it asked for out of reach of a second capture, until the provider says.
        [$order] = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = new \RuntimeException('cURL error 28: timeout on https://psp.example/capture?api_key=SECRET');

        try {
            $this->service->capture($order);
            self::fail('The caller must learn that the outcome is unknown.');
        } catch (PaymentProviderUnreachableException $exception) {
            self::assertStringNotContainsString('SECRET', $exception->getMessage(), 'What the caller shows says what happened, not what the module sent.');
            self::assertStringContainsString('timeout', (string) $exception->getPrevious()?->getMessage());
        }

        $line = OrderPaymentTransactionQuery::create()->findJournal($order->getId())[0];
        self::assertTrue($line->isPending());
        self::assertSame('exception', $line->getErrorCode());
        self::assertStringNotContainsString('SECRET', (string) $line->getErrorMessage(), 'The raw technical message is logged, not stored.');
        self::assertSame('0.000000', $this->totals->forOrder($order->getId())->remainingToCapture);
        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, $this->statusCodeOf($order));
    }

    public function testTheOutcomeOfALineTheProviderNeverConfirmedCanBeRecordedByHand(): void
    {
        // The provider never sent its notification; the merchant reads the outcome in the
        // provider's own back office and records it.
        [$order] = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = new \RuntimeException('timeout');

        try {
            $this->service->capture($order);
        } catch (PaymentProviderUnreachableException) {
        }

        $pending = OrderPaymentTransactionQuery::create()->findJournal($order->getId())[0];
        $event = new OrderPaymentSettlementEvent($pending, PaymentTransactionState::SUCCEEDED, 'PSP-SEEN-1', 'Seen as captured in the provider back office.');
        $this->dispatch($event, TheliaEvents::ORDER_PAYMENT_TRANSACTION_SETTLE);

        $settled = $event->getTransaction();
        self::assertTrue($settled->isSucceeded());
        self::assertSame('PSP-SEEN-1', $settled->getPspReference());
        self::assertSame('settled_by_hand', $settled->getErrorCode());
        self::assertSame('Seen as captured in the provider back office.', $settled->getErrorMessage());
        self::assertSame(OrderStatus::CODE_PAID, $this->statusCodeOf($order));
    }

    public function testALineRecordedByHandAsFailedGivesItsAmountBack(): void
    {
        [$order] = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = PaymentOperationResult::pending('CAP-NEVER');
        $pending = $this->service->capture($order);

        $this->dispatch(new OrderPaymentSettlementEvent($pending, PaymentTransactionState::FAILED), TheliaEvents::ORDER_PAYMENT_TRANSACTION_SETTLE);

        self::assertSame('120.000000', $this->totals->forOrder($order->getId())->remainingToCapture);
        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, $this->statusCodeOf($order));
    }

    public function testAModuleAnsweringSucceededWithoutAReferenceLeavesTheLineForTheNotification(): void
    {
        [$order] = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = PaymentOperationResult::succeeded();

        $capture = $this->service->capture($order, 40.0);

        self::assertTrue($capture->isPending());
        self::assertSame('missing_reference', $capture->getErrorCode());

        $this->recorder->recordCapture($order, 40, 'PSP-LATE-40', moduleCode: DeferredCapturePaymentModule::getModuleCode());

        self::assertSame('40.000000', $this->totals->forOrder($order->getId())->captured, 'The notification settles the line rather than writing a second capture.');
    }

    public function testAModuleAnsweringWithAReferenceTooLongLeavesTheLineForTheNotification(): void
    {
        [$order] = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = PaymentOperationResult::succeeded(str_repeat('R', 120));

        $capture = $this->service->capture($order, 40.0);

        self::assertTrue($capture->isPending());
        self::assertSame('invalid_reference', $capture->getErrorCode());
    }

    public function testAPaymentExceptionThatIsNotARefusalLeavesTheLinePending(): void
    {
        // Only a refusal says the provider took nothing. Any other failure of the module,
        // even one it words as a payment failure, may come after the provider was called.
        [$order] = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = new PaymentException('Could not parse the provider answer');

        try {
            $this->service->capture($order);
            self::fail('The caller must learn that the outcome is unknown.');
        } catch (PaymentProviderUnreachableException) {
        }

        self::assertTrue(OrderPaymentTransactionQuery::create()->findJournal($order->getId())[0]->isPending());
    }

    public function testAListenerFailingAfterTheProviderTookTheMoneyDoesNotFailTheCapture(): void
    {
        // The money is taken and the line says so: a listener that breaks afterwards is
        // logged, it does not tell the merchant the capture failed and invite a second one.
        [$order] = $this->authorizedOrder(120);
        $listener = static function (OrderPaymentTransactionEvent $event): void {
            if ($event->getTransaction()->isSucceeded()) {
                throw new \RuntimeException('Listener down');
            }
        };
        $this->dispatcher->addListener(TheliaEvents::ORDER_PAYMENT_TRANSACTION_RECORDED, $listener, 255);

        try {
            $capture = $this->service->capture($order);
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::ORDER_PAYMENT_TRANSACTION_RECORDED, $listener);
        }

        self::assertTrue($capture->isSucceeded());
        self::assertSame('120.000000', $this->totals->forOrder($order->getId())->captured);
    }

    public function testAJournalBusyWhenTheProviderAnswersLeavesTheLineForTheNotification(): void
    {
        // The provider took the money; another worker holds the journal past the wait.
        // The capture is not reported as failed: the line waits, with its reference, for
        // the notification to settle it.
        [$order] = $this->authorizedOrder(120);
        $configuration = Propel::getServiceContainer()->getConnectionManager(OrderPaymentTransactionTableMap::DATABASE_NAME)->getConfiguration();
        $otherWorker = new \PDO($configuration['dsn'], $configuration['user'] ?? null, $configuration['password'] ?? null);
        DeferredCapturePaymentModule::$whileCapturing = static function () use ($otherWorker, $order): void {
            $otherWorker->query("SELECT GET_LOCK('".PaymentJournalLock::nameFor((int) $order->getId())."', 0)");
        };

        try {
            $capture = $this->service->capture($order);
        } finally {
            $otherWorker->query("SELECT RELEASE_LOCK('".PaymentJournalLock::nameFor((int) $order->getId())."')");
        }

        self::assertTrue($capture->isPending());
        self::assertSame('journal_busy', $capture->getErrorCode());
        self::assertStringContainsString('CAP-'.$capture->getId(), (string) $capture->getErrorMessage());

        $this->recorder->recordCapture($order, 120, 'CAP-'.$capture->getId(), moduleCode: DeferredCapturePaymentModule::getModuleCode());

        $capture->reload();
        self::assertTrue($capture->isSucceeded());
    }

    public function testAModuleThatRefusesLeavesAFailedLineWithItsMessage(): void
    {
        [$order] = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = new PaymentRefusedException('Authorization expired');

        try {
            $this->service->capture($order);
            self::fail('The refusal must reach the caller.');
        } catch (PaymentRefusedException) {
        }

        $line = OrderPaymentTransactionQuery::create()->findJournal($order->getId())[0];
        self::assertTrue($line->isFailed());
        self::assertSame('Authorization expired', $line->getErrorMessage());
        self::assertSame('120.000000', $this->totals->forOrder($order->getId())->remainingToCapture);
    }

    public function testAPendingCaptureKeepsASecondCaptureFromCallingTheProviderAgain(): void
    {
        [$order] = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = PaymentOperationResult::pending('CAP-ASYNC');
        $this->service->capture($order);

        try {
            $this->service->capture($order);
            self::fail('Nothing is left to capture while the first capture is pending.');
        } catch (InvalidPaymentAmountException) {
        }

        self::assertCount(1, DeferredCapturePaymentModule::$captureCalls);
    }

    public function testTheSameAmountAskedTwiceInARowIsNotTakenTwice(): void
    {
        [$order] = $this->authorizedOrder(120);
        $this->service->capture($order, 50.0);

        try {
            $this->service->capture($order, 50.0);
            self::fail('A repeated capture of the same amount is refused.');
        } catch (DuplicateCaptureException) {
        }

        self::assertCount(1, DeferredCapturePaymentModule::$captureCalls);
        self::assertSame('70.000000', $this->totals->forOrder($order->getId())->remainingToCapture);
    }

    public function testAnAmountWithMoreDecimalsThanTheCurrencyIsRefused(): void
    {
        [$order] = $this->authorizedOrder(120);

        try {
            $this->service->capture($order, 10.005);
            self::fail('Half a cent cannot be captured.');
        } catch (InvalidPaymentAmountException) {
        }

        self::assertSame([], DeferredCapturePaymentModule::$captureCalls);
    }

    public function testAVoidDuringAPendingCaptureReleasesOnlyWhatThatCaptureDidNotAskFor(): void
    {
        [$order] = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = PaymentOperationResult::pending('CAP-ASYNC');
        $this->service->capture($order, 50.0);

        $void = $this->service->voidAuthorization($order);

        self::assertSame('70.000000', $void->getAmount());
    }

    public function testCancellingAnOrderReleasesWhatItsAuthorizationStillHolds(): void
    {
        [$order] = $this->authorizedOrder(120);

        $event = (new OrderEvent($order))->setStatus((int) OrderStatusQuery::getCancelledStatus()->getId());
        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertSame([$order->getId()], DeferredCapturePaymentModule::$voidCalls);
        self::assertSame('0.000000', $this->totals->forOrder($order->getId())->remainingToCapture);
        self::assertSame(OrderStatus::CODE_CANCELED, $this->statusCodeOf($order));
    }

    public function testAnAuthorizationConfirmedAfterTheOrderWasCancelledIsReleased(): void
    {
        // The buyer gave up, the order was cancelled, then the provider confirmed the
        // reservation: nothing will ever capture it, so it is released at once.
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => DeferredCapturePaymentModule::getModuleCode(), 'statusCode' => OrderStatus::CODE_CANCELED]);

        $this->recorder->recordAuthorization($order, 120, 'AUTH-LATE-'.$order->getId(), moduleCode: DeferredCapturePaymentModule::getModuleCode());

        self::assertSame([(int) $order->getId()], DeferredCapturePaymentModule::$voidCalls);
        self::assertSame('0.000000', $this->totals->forOrder($order->getId())->remainingToCapture);
        self::assertSame(OrderStatus::CODE_CANCELED, $this->statusCodeOf($order));
    }

    public function testAModuleThatCannotBeInstantiatedIsReportedNotDisguisedAsUnsupported(): void
    {
        $broken = (new Module())
            ->setCode('BrokenPayment')
            ->setFullNamespace('Thelia\\Tests\\Support\\Payment\\NoSuchModule')
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED);
        $broken->save($this->getPropelConnection());
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => 'BrokenPayment']);

        self::assertFalse($this->service->supportsCapture($order), 'The order sheet still renders.');

        $this->expectException(\ReflectionException::class);

        $this->service->capture($order);
    }

    public function testAModuleThatAnswersPendingLeavesTheLinePendingWithItsReference(): void
    {
        [$order] = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = PaymentOperationResult::pending('CAP-ASYNC');

        $capture = $this->service->capture($order);

        self::assertTrue($capture->isPending());
        self::assertSame('CAP-ASYNC', $capture->getPspReference());
        $totals = $this->totals->forOrder($order->getId());
        self::assertSame('0.000000', $totals->captured, 'A pending capture is not reported as captured.');
        self::assertSame('120.000000', $totals->pendingCapture);
        self::assertSame('0.000000', $totals->remainingToCapture, 'What it asked for is out of reach of another capture.');
        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, $this->statusCodeOf($order));
    }

    public function testAModuleThatTakesThePriceAtOnceCannotBeCapturedByHand(): void
    {
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => 'Cheque']);

        self::assertFalse($this->service->supportsCapture($order));

        $this->expectException(DeferredCaptureNotSupportedException::class);

        $this->service->capture($order);
    }

    public function testNothingToCaptureIsRefused(): void
    {
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => DeferredCapturePaymentModule::getModuleCode()]);

        self::assertTrue($this->service->supportsCapture($order));

        $this->expectException(InvalidPaymentAmountException::class);

        $this->service->capture($order);
    }

    public function testAVoidReleasesTheHoldAndSendsTheOrderBackToUnpaid(): void
    {
        [$order, $authorization] = $this->authorizedOrder(120);

        $void = $this->service->voidAuthorization($order);

        self::assertTrue($void->isSucceeded());
        self::assertSame('120.000000', $void->getAmount());
        self::assertSame($authorization->getId(), $void->getParentId());
        self::assertSame('0.000000', $this->totals->forOrder($order->getId())->remainingToCapture);
        self::assertSame(OrderStatus::CODE_NOT_PAID, $this->statusCodeOf($order));
    }

    public function testAModuleAnsweringWithAReferenceAlreadyUsedLeavesTheLineWaitingForTheProvider(): void
    {
        [$order] = $this->authorizedOrder(120);
        $first = $this->service->capture($order, 50.0);
        DeferredCapturePaymentModule::$nextCaptureAnswer = PaymentOperationResult::succeeded((string) $first->getPspReference());

        try {
            $this->service->capture($order, 70.0);
            self::fail('A reference another capture carries cannot settle this one.');
        } catch (ConflictingPaymentReferenceException) {
        }

        $line = OrderPaymentTransactionQuery::create()->findJournal($order->getId())[0];
        self::assertTrue($line->isPending(), 'The module may have taken the money: the line waits for the provider.');
        self::assertNull($line->getPspReference());
        self::assertSame('conflicting_reference', $line->getErrorCode());
        self::assertStringContainsString((string) $first->getPspReference(), (string) $line->getErrorMessage());

        // The provider's notification, with the reference it really gave, settles it.
        $this->recorder->recordCapture($order, 70, 'PSP-REAL-70', moduleCode: DeferredCapturePaymentModule::getModuleCode());

        $line->reload();
        self::assertTrue($line->isSucceeded());
        self::assertSame('PSP-REAL-70', $line->getPspReference());
        self::assertSame(OrderStatus::CODE_PAID, $this->statusCodeOf($order));
    }

    /**
     * @return array{Order, OrderPaymentTransaction}
     */
    private function authorizedOrder(float $total): array
    {
        $order = $this->factory->order(null, ['postage' => $total, 'paymentModuleCode' => DeferredCapturePaymentModule::getModuleCode()]);

        $authorization = $this->recorder->recordAuthorization(
            $order,
            $total,
            'AUTH-'.$order->getId(),
            moduleCode: DeferredCapturePaymentModule::getModuleCode(),
        );

        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, $this->statusCodeOf($order), 'The authorization puts the order on hold.');

        return [$order, $authorization];
    }

    private function statusCodeOf(Order $order): string
    {
        $order->reload();

        return (string) $order->getOrderStatus()->getCode();
    }

    private function registerTheDeferredCaptureModule(): void
    {
        if (null !== ModuleQuery::create()->findOneByCode(DeferredCapturePaymentModule::getModuleCode())) {
            return;
        }

        (new Module())
            ->setCode(DeferredCapturePaymentModule::getModuleCode())
            ->setFullNamespace(DeferredCapturePaymentModule::class)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->save($this->getPropelConnection());
    }
}
