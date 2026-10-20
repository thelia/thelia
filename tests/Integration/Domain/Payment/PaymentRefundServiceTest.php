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

use Thelia\Core\Event\Order\OrderPaymentRefundEvent;
use Thelia\Core\Event\Order\OrderRefundedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\DTO\PaymentOperationResult;
use Thelia\Domain\Payment\Enum\PaymentTransactionState;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Domain\Payment\Enum\RefundReason;
use Thelia\Domain\Payment\Exception\DuplicateRefundException;
use Thelia\Domain\Payment\Exception\InvalidPaymentAmountException;
use Thelia\Domain\Payment\Exception\PaymentProviderUnreachableException;
use Thelia\Domain\Payment\Exception\PaymentRefusedException;
use Thelia\Domain\Payment\Exception\RefundNotSupportedException;
use Thelia\Domain\Payment\Service\PaymentCaptureService;
use Thelia\Domain\Payment\Service\PaymentRefundService;
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;
use Thelia\Domain\Payment\Service\PaymentTransactionTotalsReader;
use Thelia\Domain\SaleUnit\Config\SaleUnitConfigScope;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Model\OrderStatus;
use Thelia\Module\BaseModule;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Payment\DeferredCapturePaymentModule;

/**
 * Giving money back, through the module that took it or, for one that cannot, written by
 * hand: bounded by what the journal says was collected, written pending before the provider
 * is called, and followed by the order once nothing collected is left.
 */
final class PaymentRefundServiceTest extends ActionIntegrationTestCase
{
    private PaymentRefundService $service;

    private PaymentTransactionRecorder $recorder;

    private PaymentTransactionTotalsReader $totals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(PaymentRefundService::class);
        $this->recorder = $this->getService(PaymentTransactionRecorder::class);
        $this->totals = $this->getService(PaymentTransactionTotalsReader::class);
        $this->registerTheModule();
        DeferredCapturePaymentModule::reset();
    }

    protected function tearDown(): void
    {
        DeferredCapturePaymentModule::reset();
        parent::tearDown();
    }

    public function testAFullRefundGivesEverythingBackAndRefundsTheOrder(): void
    {
        $order = $this->paidOrder(100);
        $announced = [];
        $listener = static function (OrderRefundedEvent $event) use (&$announced): void {
            $announced[] = [$event->getTransaction()->getAmount(), $event->getReason(), $event->isOffline()];
        };
        $this->dispatcher->addListener(TheliaEvents::ORDER_REFUNDED, $listener);

        try {
            $refund = $this->service->refund($order, null, RefundReason::Returned, 'Parcel came back');
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::ORDER_REFUNDED, $listener);
        }

        self::assertTrue($refund->isSucceeded());
        self::assertSame(PaymentTransactionType::REFUND->value, $refund->getType());
        self::assertSame('100.000000', $refund->getAmount());
        self::assertSame('REF-'.$refund->getId(), $refund->getPspReference());
        self::assertSame([['order' => (int) $order->getId(), 'amount' => 100.0, 'transaction' => (int) $refund->getId(), 'reason' => 'returned', 'comment' => 'Parcel came back']], DeferredCapturePaymentModule::$refundCalls);
        self::assertSame(OrderStatus::CODE_REFUNDED, $this->statusOf($order));
        self::assertSame([['100.000000', RefundReason::Returned, false]], $announced);
    }

    public function testAPartialRefundLeavesTheOrderPaidAndTheRestRefundable(): void
    {
        $order = $this->paidOrder(100);

        $this->service->refund($order, 30.0, RefundReason::Goodwill);

        $totals = $this->totals->forOrder((int) $order->getId());
        self::assertSame('30.000000', $totals->refunded);
        self::assertSame('70.000000', $totals->refundable());
        self::assertSame(OrderStatus::CODE_PAID, $this->statusOf($order));
    }

    public function testMoreThanIsLeftToRefundIsRefusedBeforeTheProviderIsCalled(): void
    {
        $order = $this->paidOrder(100);
        $this->service->refund($order, 30.0, RefundReason::Goodwill);

        try {
            $this->service->refund($order, 80.0, RefundReason::Goodwill);
            self::fail('Only 70.00 is left to refund.');
        } catch (InvalidPaymentAmountException) {
        }

        self::assertCount(1, DeferredCapturePaymentModule::$refundCalls);
    }

    public function testWhatIsRefundableIsWhatWasCollectedNotTheOrderTotal(): void
    {
        // Authorized for 120, only 50 captured: 50 can be given back.
        $order = $this->factory->order(null, ['postage' => 120, 'paymentModuleCode' => DeferredCapturePaymentModule::getModuleCode()]);
        $this->recorder->recordAuthorization($order, 120, 'AUTH-'.$order->getId(), moduleCode: DeferredCapturePaymentModule::getModuleCode());
        $this->getService(PaymentCaptureService::class)->capture($order, 50.0);

        $this->expectException(InvalidPaymentAmountException::class);

        $this->service->refund($order, 60.0, RefundReason::Other);
    }

    public function testTheSameRefundAskedAgainRightAwayIsNotMadeTwice(): void
    {
        $order = $this->paidOrder(100);
        $this->service->refund($order, 20.0, RefundReason::Goodwill);

        $this->expectException(DuplicateRefundException::class);

        $this->service->refund($order, 20.0, RefundReason::Goodwill);
    }

    public function testARefusalOfTheProviderLeavesAFailedLineAndTheOrderAsItWas(): void
    {
        $order = $this->paidOrder(100);
        DeferredCapturePaymentModule::$nextRefundAnswer = new PaymentRefusedException('Refund window closed');

        try {
            $this->service->refund($order, 40.0, RefundReason::Other);
            self::fail('The refusal must reach the merchant.');
        } catch (PaymentRefusedException) {
        }

        $line = OrderPaymentTransactionQuery::create()->findJournal((int) $order->getId())[0];
        self::assertTrue($line->isFailed());
        self::assertSame('Refund window closed', $line->getErrorMessage());
        self::assertSame('100.000000', $this->totals->forOrder((int) $order->getId())->refundable());
        self::assertSame(OrderStatus::CODE_PAID, $this->statusOf($order));
    }

    public function testARefusalWithTheCodeOfTheProviderWritesThatCode(): void
    {
        $order = $this->paidOrder(100);
        DeferredCapturePaymentModule::$nextRefundAnswer = PaymentRefusedException::withProviderCode('Refund window closed', 'REFUND_WINDOW');

        try {
            $this->service->refund($order, 40.0, RefundReason::Other);
            self::fail('The refusal must reach the merchant.');
        } catch (PaymentRefusedException) {
        }

        $line = OrderPaymentTransactionQuery::create()->findJournal((int) $order->getId())[0];
        self::assertTrue($line->isFailed());
        self::assertSame('REFUND_WINDOW', $line->getErrorCode());
    }

    public function testAPaymentTheProviderGivesBackInFullOnlyIsNotRefundedInPart(): void
    {
        // A card payment not remitted yet is cancelled whole: a part would be refused by the provider.
        DeferredCapturePaymentModule::$refundsOnlyInFull = true;
        $order = $this->paidOrder(100);

        self::assertTrue($this->service->refundsOnlyInFull($order));

        try {
            $this->service->refund($order, 40.0, RefundReason::Goodwill);
            self::fail('A part is refused before the provider is called.');
        } catch (InvalidPaymentAmountException $refused) {
            self::assertStringContainsString('in full', $refused->getMessage());
        }

        self::assertSame([], DeferredCapturePaymentModule::$refundCalls);
        self::assertTrue($this->service->refund($order, 100.0, RefundReason::Goodwill)->isSucceeded());
    }

    public function testTheModuleRefundsWithTheConfigurationOfTheSaleUnitOfTheOrder(): void
    {
        $order = $this->paidOrder(100);
        $saleUnitId = (int) $this->factory->saleUnit()->getId();
        $order->setSaleUnitId($saleUnitId)->save($this->getPropelConnection());
        DeferredCapturePaymentModule::$scopes = [];

        $this->service->refund($order, 40.0, RefundReason::Goodwill);

        $calls = array_column(DeferredCapturePaymentModule::$scopes, 'call');
        self::assertContains('supportsRefund', $calls);
        self::assertContains('refund', $calls);
        self::assertSame([$saleUnitId], array_values(array_unique(array_column(DeferredCapturePaymentModule::$scopes, 'saleUnit'))), 'Every call runs with the configuration of the sale unit of the order.');
        self::assertNull(SaleUnitConfigScope::current());
    }

    public function testWhatTheRefundAnnouncesRunsWithTheConfigurationOfTheSaleUnitOfTheOrder(): void
    {
        $order = $this->paidOrder(100);
        $saleUnitId = (int) $this->factory->saleUnit()->getId();
        $order->setSaleUnitId($saleUnitId)->save($this->getPropelConnection());
        $chequeOrder = $this->factory->order(null, ['postage' => 100]);
        $chequeOrder->setSaleUnitId($saleUnitId)->save($this->getPropelConnection());
        $this->recorder->recordCapture($chequeOrder, 100, 'CHQ-2', moduleCode: 'Cheque');
        $seen = [];
        $listener = static function () use (&$seen): void {
            $seen[] = SaleUnitConfigScope::current();
        };
        $this->dispatcher->addListener(TheliaEvents::ORDER_PAYMENT_TRANSACTION_RECORDED, $listener);
        $this->dispatcher->addListener(TheliaEvents::ORDER_UPDATE_STATUS, $listener);

        try {
            $this->service->refund($order, null, RefundReason::Returned);
            $this->service->recordOfflineRefund($chequeOrder, null, RefundReason::Goodwill);
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::ORDER_PAYMENT_TRANSACTION_RECORDED, $listener);
            $this->dispatcher->removeListener(TheliaEvents::ORDER_UPDATE_STATUS, $listener);
        }

        self::assertNotEmpty($seen);
        self::assertSame([$saleUnitId], array_values(array_unique($seen)), 'Every announcement of the refund runs with the configuration of the order\'s unit.');
        self::assertNull(SaleUnitConfigScope::current());
    }

    public function testTheCommentSentToTheProviderIsOneLineOfVisibleText(): void
    {
        $order = $this->paidOrder(100);

        $this->service->refund($order, 40.0, RefundReason::Goodwill, "  Parcel\r\ncame\u{202E}back\x07 ".str_repeat('x', 300)."\xFF");

        $comment = DeferredCapturePaymentModule::$refundCalls[0]['comment'];
        self::assertIsString($comment);
        self::assertStringStartsWith('Parcel came?back? x', $comment);
        self::assertSame(255, mb_strlen($comment));
        self::assertTrue(mb_check_encoding($comment, 'UTF-8'));
    }

    public function testACallThatGotNoAnswerLeavesTheRefundPending(): void
    {
        $order = $this->paidOrder(100);
        DeferredCapturePaymentModule::$nextRefundAnswer = new \RuntimeException('timeout');

        try {
            $this->service->refund($order, 40.0, RefundReason::Other);
            self::fail('The outcome is unknown.');
        } catch (PaymentProviderUnreachableException) {
        }

        self::assertTrue(OrderPaymentTransactionQuery::create()->findJournal((int) $order->getId())[0]->isPending());
        self::assertSame('60.000000', $this->totals->forOrder((int) $order->getId())->refundable(), 'A refund waiting for its answer counts as given.');
    }

    public function testARefundTheProviderConfirmsLaterIsAnnouncedOnceWithoutAReason(): void
    {
        $order = $this->paidOrder(100);
        DeferredCapturePaymentModule::$nextRefundAnswer = PaymentOperationResult::pending('REF-LATE');

        $announced = $this->announcedRefunds(function () use ($order): void {
            $pending = $this->service->refund($order, 40.0, RefundReason::Goodwill, 'Late parcel');
            self::assertTrue($pending->isPending());

            // The provider's notification, in a request of its own, then replayed.
            $this->recorder->settle($pending, PaymentTransactionState::SUCCEEDED, 'REF-LATE', moduleCode: DeferredCapturePaymentModule::getModuleCode());
            $this->recorder->settle($pending, PaymentTransactionState::SUCCEEDED, 'REF-LATE', moduleCode: DeferredCapturePaymentModule::getModuleCode());
        });

        self::assertSame([['40.000000', null, null, false]], $announced);
    }

    public function testARefundMadeFromTheProvidersBackOfficeIsAnnouncedOnce(): void
    {
        $order = $this->paidOrder(100);

        $announced = $this->announcedRefunds(function () use ($order): void {
            $this->recorder->recordRefund($order, 25, 'PSP-BO-1', moduleCode: DeferredCapturePaymentModule::getModuleCode());
            $this->recorder->recordRefund($order, 25, 'PSP-BO-1', moduleCode: DeferredCapturePaymentModule::getModuleCode());
        });

        self::assertSame([['25.000000', null, null, false]], $announced);
    }

    public function testARefundRecordedByHandIsAnnouncedWithItsReason(): void
    {
        $order = $this->paidOrder(100);

        $announced = $this->announcedRefunds(function () use ($order): void {
            $this->service->recordOfflineRefund($order, 100.0, RefundReason::Cancellation, 'Bank transfer');
        });

        self::assertSame([['100.000000', RefundReason::Cancellation, 'Bank transfer', true]], $announced);
    }

    public function testARefusedRefundIsNotAnnounced(): void
    {
        $order = $this->paidOrder(100);
        DeferredCapturePaymentModule::$nextRefundAnswer = PaymentOperationResult::failed('05', 'Refused');

        self::assertSame([], $this->announcedRefunds(function () use ($order): void {
            $this->service->refund($order, 40.0, RefundReason::Other);
        }));
    }

    public function testAModuleThatCannotRefundIsNamedInTheRefusal(): void
    {
        $order = $this->paidOrder(100);
        DeferredCapturePaymentModule::$refunds = false;

        $this->expectException(RefundNotSupportedException::class);
        $this->expectExceptionMessage(DeferredCapturePaymentModule::getModuleCode());

        $this->service->refund($order, 40.0, RefundReason::Other);
    }

    public function testADeactivatedModuleIsNotAskedToRefund(): void
    {
        // Its services are no longer compiled in the container: calling it would fail
        // half-way, after the pending line is written.
        $order = $this->paidOrder(100);
        ModuleQuery::create()
            ->findOneByCode(DeferredCapturePaymentModule::getModuleCode())
            ->setActivate(BaseModule::IS_NOT_ACTIVATED)
            ->save($this->getPropelConnection());

        self::assertFalse($this->service->supportsRefund($order));

        try {
            $this->service->refund($order, 40.0, RefundReason::Other);
            self::fail('A deactivated module was asked to refund.');
        } catch (RefundNotSupportedException $exception) {
            self::assertStringContainsString(DeferredCapturePaymentModule::getModuleCode(), $exception->getMessage());
        }

        self::assertSame([], DeferredCapturePaymentModule::$refundCalls);
    }

    public function testARefundMadeOutsideTheProviderIsWrittenByHand(): void
    {
        // A cheque order: refunded by bank transfer, recorded for the books.
        $order = $this->factory->order(null, ['postage' => 100]);
        $this->recorder->recordCapture($order, 100, 'CHQ-1', moduleCode: 'Cheque');

        $refund = $this->service->recordOfflineRefund($order, 100.0, RefundReason::Cancellation, 'Bank transfer 2026-10-09');

        self::assertTrue($refund->isSucceeded());
        self::assertSame(PaymentRefundService::ERROR_CODE_OFFLINE, $refund->getErrorCode());
        self::assertSame('Bank transfer 2026-10-09', $refund->getErrorMessage());
        self::assertSame('0.000000', $this->totals->forOrder((int) $order->getId())->refundable());
    }

    public function testTheBackOfficeAndTheApiRefundThroughTheEvent(): void
    {
        $order = $this->paidOrder(100);

        $online = new OrderPaymentRefundEvent($order, 10.0, RefundReason::Goodwill, 'Sorry for the delay');
        $this->dispatch($online, TheliaEvents::ORDER_PAYMENT_REFUND);
        $offline = new OrderPaymentRefundEvent($order, 15.0, RefundReason::Other, 'Voucher', true);
        $this->dispatch($offline, TheliaEvents::ORDER_PAYMENT_REFUND);

        self::assertSame('REF-'.$online->getTransaction()->getId(), $online->getTransaction()->getPspReference());
        self::assertSame(PaymentRefundService::ERROR_CODE_OFFLINE, $offline->getTransaction()->getErrorCode());
        self::assertCount(1, DeferredCapturePaymentModule::$refundCalls, 'The refund recorded by hand calls no provider.');
    }

    /**
     * @return list<array{string, ?RefundReason, ?string, bool}>
     */
    private function announcedRefunds(callable $work): array
    {
        $announced = [];
        $listener = static function (OrderRefundedEvent $event) use (&$announced): void {
            $announced[] = [(string) $event->getTransaction()->getAmount(), $event->getReason(), $event->getComment(), $event->isOffline()];
        };
        $this->dispatcher->addListener(TheliaEvents::ORDER_REFUNDED, $listener);

        try {
            $work();
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::ORDER_REFUNDED, $listener);
        }

        return $announced;
    }

    private function paidOrder(float $total): Order
    {
        $order = $this->factory->order(null, ['postage' => $total, 'paymentModuleCode' => DeferredCapturePaymentModule::getModuleCode()]);
        $this->recorder->recordAuthorization($order, $total, 'AUTH-'.$order->getId(), moduleCode: DeferredCapturePaymentModule::getModuleCode());
        $this->getService(PaymentCaptureService::class)->capture($order);
        self::assertSame(OrderStatus::CODE_PAID, $this->statusOf($order));

        return $order;
    }

    private function statusOf(Order $order): string
    {
        $order->reload();

        return (string) $order->getOrderStatus()->getCode();
    }

    private function registerTheModule(): void
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
