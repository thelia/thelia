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

use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Service\OrderStatusTransitionWriter;
use Thelia\Domain\Payment\Enum\PaymentTransactionState;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Domain\Payment\Exception\CaptureExceedsAuthorizationException;
use Thelia\Domain\Payment\Exception\InvalidPaymentAmountException;
use Thelia\Domain\Payment\Exception\MissingProviderReferenceException;
use Thelia\Domain\Payment\Exception\PaymentException;
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;
use Thelia\Domain\Payment\Service\PaymentTransactionTotalsReader;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class PaymentTransactionRecorderTest extends ActionIntegrationTestCase
{
    private PaymentTransactionRecorder $recorder;

    private PaymentTransactionTotalsReader $totals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = $this->getService(PaymentTransactionRecorder::class);
        $this->totals = $this->getService(PaymentTransactionTotalsReader::class);
    }

    public function testAnAuthorizationThenAPartialCaptureThenTheBalanceLeaveThreeLinesAndNothingToCapture(): void
    {
        $order = $this->order(120);

        $authorization = $this->recorder->recordAuthorization($order, 120, 'AUTH-1', moduleCode: 'Cheque');

        $totals = $this->totals->forOrder($order->getId());
        self::assertSame('120.000000', $totals->authorized);
        self::assertSame('120.000000', $totals->remainingToCapture);

        $this->recorder->recordCapture($order, 50, 'CAP-1', authorization: $authorization, moduleCode: 'Cheque');

        $totals = $this->totals->forOrder($order->getId());
        self::assertSame('50.000000', $totals->captured);
        self::assertSame('70.000000', $totals->remainingToCapture);

        $balance = $this->recorder->recordCapture($order, 70.0, 'CAP-2', authorization: $authorization, moduleCode: 'Cheque');

        $totals = $this->totals->forOrder($order->getId());
        self::assertSame('120.000000', $totals->captured);
        self::assertSame('0.000000', $totals->remainingToCapture);
        self::assertFalse($totals->hasSomethingLeftToCapture());
        self::assertSame($authorization->getId(), $balance->getParentId());
        self::assertCount(3, OrderPaymentTransactionQuery::create()->findJournal($order->getId()));
    }

    public function testACaptureAboveWhatTheAuthorizationStillHoldsIsRefused(): void
    {
        $order = $this->order(120);
        $this->recorder->recordAuthorization($order, 120, 'AUTH-1');
        $this->recorder->recordCapture($order, 50, 'CAP-1');

        try {
            $this->recorder->recordCapture($order, 80, 'CAP-2');
            self::fail('A capture of 80 on a remainder of 70 must be refused.');
        } catch (CaptureExceedsAuthorizationException $exception) {
            self::assertSame('80.000000', $exception->getRequestedAmount());
            self::assertSame('70.000000', $exception->getRemainingToCapture());
        }

        self::assertCount(2, OrderPaymentTransactionQuery::create()->findJournal($order->getId()));
        self::assertSame('70.000000', $this->totals->forOrder($order->getId())->remainingToCapture);
    }

    public function testACaptureWithoutAnAuthorizationIsThePaymentItself(): void
    {
        $order = $this->order(120);

        $this->recorder->recordCapture($order, 120, 'PSP-4F2A-7C10');

        $totals = $this->totals->forOrder($order->getId());
        self::assertFalse($totals->hasAuthorization());
        self::assertSame('120.000000', $totals->captured);
        self::assertSame('0.000000', $totals->remainingToCapture);
    }

    public function testAReplayedProviderReferenceWritesNoSecondLine(): void
    {
        $order = $this->order(120);

        $first = $this->recorder->recordCapture($order, 120, 'PSP-4F2A-7C10');
        $replayed = $this->recorder->recordCapture($order, 120, 'PSP-4F2A-7C10');

        self::assertSame($first->getId(), $replayed->getId());
        self::assertCount(1, OrderPaymentTransactionQuery::create()->findJournal($order->getId()));
    }

    public function testTheSameReferenceOnAnotherMovementIsAnotherLine(): void
    {
        $order = $this->order(120);

        $this->recorder->recordAuthorization($order, 120, 'TRX-1');
        $this->recorder->recordCapture($order, 120, 'TRX-1');

        self::assertCount(2, OrderPaymentTransactionQuery::create()->findJournal($order->getId()));
    }

    public function testAnAlphanumericReferenceIsKeptAsTheProviderGaveIt(): void
    {
        $order = $this->order(120);

        $line = $this->recorder->recordCapture($order, 120, ' PSP-4F2A-7C10 ');

        self::assertSame('PSP-4F2A-7C10', $line->getPspReference());
    }

    public function testAFailedAuthorizationKeepsItsMessageAndMovesNothing(): void
    {
        $order = $this->order(120);

        $line = $this->recorder->recordAuthorization(
            $order,
            120,
            'AUTH-REFUSED',
            PaymentTransactionState::FAILED,
            'Cheque',
            '05',
            'Do not honor',
        );

        self::assertTrue($line->isFailed());
        self::assertSame('05', $line->getErrorCode());
        self::assertSame('Do not honor', $line->getErrorMessage());
        self::assertSame(OrderStatus::CODE_NOT_PAID, $this->reload($order)->getOrderStatus()->getCode());
        self::assertSame('0.000000', $this->totals->forOrder($order->getId())->authorized);
    }

    public function testARefundAboveWhatWasTakenIsRefused(): void
    {
        $order = $this->order(120);
        $this->recorder->recordCapture($order, 120, 'CAP-1');
        $this->recorder->recordRefund($order, 20, 'REF-1');

        $this->expectException(InvalidPaymentAmountException::class);

        $this->recorder->recordRefund($order, 100.01, 'REF-2');
    }

    public function testAnAuthorizationNeedsAPositiveAmount(): void
    {
        $this->expectException(InvalidPaymentAmountException::class);

        $this->recorder->recordAuthorization($this->order(120), 0);
    }

    public function testTheAuthorIsTheModuleWhenNoAdministratorIsInSession(): void
    {
        $order = $this->order(120);

        $line = $this->recorder->recordCapture($order, 120, 'CAP-1', moduleCode: 'Cheque');

        self::assertSame(OrderHistoryActorType::MODULE->value, $line->getActorType());
        self::assertSame('Cheque', $line->getActorLabel());
        self::assertNull($line->getAdminId());
        self::assertSame($order->getPaymentModuleId(), $line->getPaymentModuleId());
        self::assertSame($order->getCurrencyId(), $line->getCurrencyId());
    }

    public function testAPendingLineIsSettledOnceWithItsOutcome(): void
    {
        $order = $this->order(120);
        $pending = $this->recorder->recordCapture($order, 120, null, PaymentTransactionState::PENDING);

        self::assertTrue($pending->isPending());
        self::assertSame('0.000000', $this->totals->forOrder($order->getId())->captured, 'A pending line counts for nothing yet.');

        $settled = $this->recorder->settle($pending, PaymentTransactionState::SUCCEEDED, 'CAP-LATE');

        self::assertSame($pending->getId(), $settled->getId());
        self::assertTrue($settled->isSucceeded());
        self::assertSame('CAP-LATE', $settled->getPspReference());
        self::assertSame('120.000000', $this->totals->forOrder($order->getId())->captured);

        $this->expectException(PaymentException::class);

        $this->recorder->settle($settled, PaymentTransactionState::FAILED);
    }

    public function testAnAuthorizationPutsAnUnpaidOrderOnHoldForCapture(): void
    {
        $order = $this->order(120);

        $this->recorder->recordAuthorization($order, 120, 'AUTH-1', moduleCode: 'Cheque');

        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, $this->reload($order)->getOrderStatus()->getCode());
        self::assertTrue($this->reload($order)->isNotPaid(false), 'The hold status stands for not_paid.');
    }

    public function testCapturingTheWholeAuthorizationPaysTheOrder(): void
    {
        $order = $this->order(120);
        $this->recorder->recordAuthorization($order, 120, 'AUTH-1', moduleCode: 'Cheque');

        $this->recorder->recordCapture($order, 50, 'CAP-1', moduleCode: 'Cheque');
        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, $this->reload($order)->getOrderStatus()->getCode(), 'A partial capture leaves the order on hold.');

        $this->recorder->recordCapture($order, 70, 'CAP-2', moduleCode: 'Cheque');
        self::assertSame(OrderStatus::CODE_PAID, $this->reload($order)->getOrderStatus()->getCode());

        // The paid status did not add a second capture for the module that keeps no
        // journal: the lines written here are the journal.
        $captures = OrderPaymentTransactionQuery::create()
            ->filterByOrderId($order->getId())
            ->filterByTypeEnum(PaymentTransactionType::CAPTURE)
            ->count();
        self::assertSame(2, $captures);
    }

    public function testAVoidOfAnUntouchedAuthorizationSendsTheOrderBackToUnpaid(): void
    {
        $order = $this->order(120);
        $this->recorder->recordAuthorization($order, 120, 'AUTH-1', moduleCode: 'Cheque');

        $void = $this->recorder->recordVoid($order, 'VOID-1', moduleCode: 'Cheque');

        self::assertSame('120.000000', $void->getAmount(), 'A void releases what was still held.');
        self::assertSame('0.000000', $this->totals->forOrder($order->getId())->remainingToCapture);
        self::assertSame(OrderStatus::CODE_NOT_PAID, $this->reload($order)->getOrderStatus()->getCode());
    }

    public function testAReplayedVoidIsAnsweredWithItsLine(): void
    {
        $order = $this->order(120);
        $this->recorder->recordAuthorization($order, 120, 'AUTH-1', moduleCode: 'Cheque');
        $void = $this->recorder->recordVoid($order, 'VOID-1', moduleCode: 'Cheque');

        $replayed = $this->recorder->recordVoid($order, 'VOID-1', moduleCode: 'Cheque');

        self::assertSame($void->getId(), $replayed->getId());
        self::assertSame(2, OrderPaymentTransactionQuery::create()->filterByOrderId($order->getId())->count());
    }

    public function testANotificationSettlesTheVoidWaitingForItsReference(): void
    {
        // The call releasing the authorization timed out: the void was left pending, with
        // nothing left to release in the totals, and without the provider's reference.
        $order = $this->order(120);
        $this->recorder->recordAuthorization($order, 120, 'AUTH-1', moduleCode: 'Cheque');
        $pending = $this->recorder->recordVoid($order, null, PaymentTransactionState::PENDING);

        $notified = $this->recorder->recordVoid($order, 'PSP-VOID-7', moduleCode: 'Cheque');

        self::assertSame($pending->getId(), $notified->getId());
        self::assertTrue($notified->isSucceeded());
        self::assertSame('120.000000', $notified->getAmount());
        self::assertSame(OrderStatus::CODE_NOT_PAID, $this->reload($order)->getOrderStatus()->getCode());
    }

    public function testANotificationSettlesThePendingVoidItsReferenceNames(): void
    {
        $order = $this->order(120);
        $this->recorder->recordAuthorization($order, 120, 'AUTH-1', moduleCode: 'Cheque');
        $pending = $this->recorder->recordVoid($order, null, PaymentTransactionState::PENDING);
        $this->recorder->attachReference($pending, 'PSP-VOID-8');

        $notified = $this->recorder->recordVoid($order, 'PSP-VOID-8', PaymentTransactionState::FAILED, moduleCode: 'Cheque');

        self::assertSame($pending->getId(), $notified->getId());
        self::assertTrue($notified->isFailed());
        self::assertSame('120.000000', $this->totals->forOrder($order->getId())->remainingToCapture, 'A void that failed released nothing.');
    }

    public function testAPendingCaptureReservesWhatItAskedFor(): void
    {
        $order = $this->order(100);
        $this->recorder->recordAuthorization($order, 100, 'AUTH-1');
        $this->recorder->recordCapture($order, 100, null, PaymentTransactionState::PENDING);

        $this->expectException(CaptureExceedsAuthorizationException::class);

        $this->recorder->recordCapture($order, 1, 'CAP-2');
    }

    public function testAnAuthorizationNeedsTheProviderReference(): void
    {
        // Without a reference a replayed authorization cannot be told from a second one,
        // and the authorized amount would double.
        $this->expectException(MissingProviderReferenceException::class);

        $this->recorder->recordAuthorization($this->order(100), 100);
    }

    public function testAReplayedReferenceLessCaptureIsTheSameLine(): void
    {
        $order = $this->order(120);

        $first = $this->recorder->recordCapture($order, 120, moduleCode: 'Cheque');
        $replayed = $this->recorder->recordCapture($order, 120, moduleCode: 'Cheque');

        self::assertSame($first->getId(), $replayed->getId());
        self::assertCount(1, OrderPaymentTransactionQuery::create()->findJournal($order->getId()));
    }

    public function testAReplayAfterAListenerFailureMovesTheOrderAtLast(): void
    {
        $order = $this->order(100);
        $failOnce = static function (): void {
            static $failed = false;

            if (!$failed) {
                $failed = true;

                throw new \RuntimeException('Listener down');
            }
        };
        $this->dispatcher->addListener(TheliaEvents::ORDER_PAYMENT_TRANSACTION_RECORDED, $failOnce, 255);

        try {
            $this->recorder->recordAuthorization($order, 100, 'AUTH-1');
            self::fail('The first notification fails on the listener.');
        } catch (\RuntimeException) {
        }

        self::assertSame(OrderStatus::CODE_NOT_PAID, $this->reload($order)->getOrderStatus()->getCode());

        // The provider replays the same notification.
        $this->recorder->recordAuthorization($order, 100, 'AUTH-1');
        $this->dispatcher->removeListener(TheliaEvents::ORDER_PAYMENT_TRANSACTION_RECORDED, $failOnce);

        self::assertCount(1, OrderPaymentTransactionQuery::create()->findJournal($order->getId()));
        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, $this->reload($order)->getOrderStatus()->getCode());
    }

    public function testASuccessReportedUnderTheReferenceOfAFailureIsRefusedExplicitly(): void
    {
        $order = $this->order(100);
        $this->recorder->recordAuthorization($order, 100, 'PI-1', PaymentTransactionState::FAILED, errorCode: '05');

        $this->expectException(PaymentException::class);

        $this->recorder->recordAuthorization($order, 100, 'PI-1');
    }

    public function testAReplayWithAnotherAmountIsRefusedExplicitly(): void
    {
        $order = $this->order(100);
        $this->recorder->recordCapture($order, 100, 'CAP-1');

        $this->expectException(PaymentException::class);

        $this->recorder->recordCapture($order, 60, 'CAP-1');
    }

    public function testAnOutcomeReportedForAPendingLineSettlesIt(): void
    {
        $order = $this->order(100);
        $this->recorder->recordAuthorization($order, 100, 'AUTH-1');
        $pending = $this->recorder->recordCapture($order, 100, null, PaymentTransactionState::PENDING);
        $this->recorder->attachReference($pending, 'CAP-ASYNC');

        $notified = $this->recorder->recordCapture($order, 100, 'CAP-ASYNC');

        self::assertSame($pending->getId(), $notified->getId());
        self::assertTrue($notified->isSucceeded());
        self::assertSame(OrderStatus::CODE_PAID, $this->reload($order)->getOrderStatus()->getCode());
    }

    public function testSettlingOnAReferenceAnotherLineCarriesIsRefusedExplicitly(): void
    {
        $order = $this->order(100);
        $this->recorder->recordAuthorization($order, 100, 'AUTH-1');
        $this->recorder->recordCapture($order, 40, 'CAP-1');
        $pending = $this->recorder->recordCapture($order, 60, null, PaymentTransactionState::PENDING);

        $this->expectException(PaymentException::class);

        $this->recorder->settle($pending, PaymentTransactionState::SUCCEEDED, 'CAP-1');
    }

    public function testProviderReferencesAreCaseSensitive(): void
    {
        $order = $this->order(100);

        $this->recorder->recordRefund($this->paid($order), 10, 'Ab1');
        $this->recorder->recordRefund($order, 10, 'aB1');

        self::assertSame(2, OrderPaymentTransactionQuery::create()->filterByOrderId($order->getId())->filterByTypeEnum(PaymentTransactionType::REFUND)->count());
    }

    public function testAPendingRefundCountsAgainstWhatCanBeGivenBack(): void
    {
        $order = $this->paid($this->order(100));
        $this->recorder->recordRefund($order, 80, null, PaymentTransactionState::PENDING);

        $this->expectException(InvalidPaymentAmountException::class);

        $this->recorder->recordRefund($order, 30, 'REF-2');
    }

    public function testReleasingTheRestOfAPartlyCapturedAuthorizationPaysTheOrder(): void
    {
        $order = $this->order(100);
        $this->recorder->recordAuthorization($order, 100, 'AUTH-1', moduleCode: 'Cheque');
        $this->recorder->recordCapture($order, 40, 'CAP-1', moduleCode: 'Cheque');

        $this->recorder->recordVoid($order, 'VOID-1', moduleCode: 'Cheque');

        self::assertSame(OrderStatus::CODE_PAID, $this->reload($order)->getOrderStatus()->getCode(), 'What was taken is the payment.');
    }

    public function testARemainderBelowTheSmallestCoinPaysTheOrder(): void
    {
        $order = $this->order(100);
        $this->recorder->recordAuthorization($order, 100, 'AUTH-1', moduleCode: 'Cheque');

        $this->recorder->recordCapture($order, 99.995, 'CAP-1', moduleCode: 'Cheque');

        self::assertSame(OrderStatus::CODE_PAID, $this->reload($order)->getOrderStatus()->getCode());
    }

    public function testATransitionTheGraphRefusesLeavesTheLineAndTheStatusAndDoesNotFailTheNotification(): void
    {
        $notPaid = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_NOT_PAID);
        $this->getService(OrderStatusTransitionWriter::class)->replaceTargets(
            (int) $notPaid->getId(),
            [(int) OrderStatusQuery::getPaidStatus()->getId(), (int) OrderStatusQuery::getCancelledStatus()->getId()],
        );
        $order = $this->order(100);

        $line = $this->recorder->recordAuthorization($order, 100, 'AUTH-1', moduleCode: 'Cheque');

        self::assertTrue($line->isSucceeded());
        self::assertSame(OrderStatus::CODE_NOT_PAID, $this->reload($order)->getOrderStatus()->getCode());
    }

    public function testALateAuthorizationLeavesAnOrderInACustomCancelledStatus(): void
    {
        $customCancelled = $this->factory->orderStatus(['equivalentCode' => OrderStatus::CODE_CANCELED]);
        $order = $this->order(100);
        $order->setStatusId($customCancelled->getId())->save();

        $this->recorder->recordAuthorization($order, 100, 'AUTH-LATE', moduleCode: 'Cheque');

        self::assertSame($customCancelled->getId(), $this->reload($order)->getStatusId());
    }

    public function testANotificationSettlesTheCaptureWaitingForItsReference(): void
    {
        // The call to the provider timed out: the line was left pending, without the
        // reference the provider gave the capture. The notification brings both.
        $order = $this->order(120);
        $this->recorder->recordAuthorization($order, 120, 'AUTH-1', moduleCode: 'Cheque');
        $pending = $this->recorder->recordCapture($order, 120, null, PaymentTransactionState::PENDING);

        $notified = $this->recorder->recordCapture($order, 120, 'PSP-CAP-77', moduleCode: 'Cheque');

        self::assertSame($pending->getId(), $notified->getId(), 'The notification settles the waiting line rather than writing a second capture.');
        self::assertTrue($notified->isSucceeded());
        self::assertSame('PSP-CAP-77', $notified->getPspReference());
        self::assertSame(2, OrderPaymentTransactionQuery::create()->filterByOrderId($order->getId())->count());
        self::assertSame(OrderStatus::CODE_PAID, $this->reload($order)->getOrderStatus()->getCode());
    }

    public function testANotificationOfAnotherAmountDoesNotSettleTheWaitingCapture(): void
    {
        $order = $this->order(120);
        $this->recorder->recordAuthorization($order, 120, 'AUTH-1', moduleCode: 'Cheque');
        $pending = $this->recorder->recordCapture($order, 70, null, PaymentTransactionState::PENDING);

        $notified = $this->recorder->recordCapture($order, 50, 'PSP-CAP-78', moduleCode: 'Cheque');

        self::assertNotSame($pending->getId(), $notified->getId());
        $pending->reload();
        self::assertTrue($pending->isPending());
    }

    public function testTwoCapturesWaitingForTheSameAmountAreNotGuessedBetween(): void
    {
        $order = $this->order(120);
        $this->recorder->recordAuthorization($order, 120, 'AUTH-1', moduleCode: 'Cheque');
        $this->recorder->recordCapture($order, 60, null, PaymentTransactionState::PENDING);
        $this->recorder->recordCapture($order, 60, null, PaymentTransactionState::PENDING);

        // Which of the two the notification is about cannot be told: nothing is settled,
        // and the capture, which the two pending lines already reserve, is refused.
        $this->expectException(CaptureExceedsAuthorizationException::class);

        $this->recorder->recordCapture($order, 60, 'PSP-CAP-79', moduleCode: 'Cheque');
    }

    private function paid(Order $order): Order
    {
        $this->recorder->recordCapture($order, 100, 'CAP-PAID');

        return $order;
    }

    private function order(float $total): Order
    {
        return $this->factory->order(null, ['postage' => $total]);
    }

    private function reload(Order $order): Order
    {
        $order->reload();

        return $order;
    }
}
