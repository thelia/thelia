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

use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Payment\Enum\PaymentTransactionState;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Domain\Payment\Exception\CaptureExceedsAuthorizationException;
use Thelia\Domain\Payment\Exception\InvalidPaymentAmountException;
use Thelia\Domain\Payment\Exception\PaymentException;
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;
use Thelia\Domain\Payment\Service\PaymentTransactionTotalsReader;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Model\OrderStatus;
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

    public function testAVoidSendsAnOrderOnHoldBackToUnpaid(): void
    {
        $order = $this->order(120);
        $this->recorder->recordAuthorization($order, 120, 'AUTH-1', moduleCode: 'Cheque');
        $this->recorder->recordCapture($order, 20, 'CAP-1', moduleCode: 'Cheque');

        $void = $this->recorder->recordVoid($order, 'VOID-1', moduleCode: 'Cheque');

        self::assertSame('100.000000', $void->getAmount(), 'A void releases what was still held.');
        self::assertSame('0.000000', $this->totals->forOrder($order->getId())->remainingToCapture);
        self::assertSame(OrderStatus::CODE_NOT_PAID, $this->reload($order)->getOrderStatus()->getCode());
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
