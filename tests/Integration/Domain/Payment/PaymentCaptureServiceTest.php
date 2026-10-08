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

use Thelia\Core\Event\Order\OrderPaymentCaptureEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\DTO\PaymentOperationResult;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Domain\Payment\Exception\CaptureExceedsAuthorizationException;
use Thelia\Domain\Payment\Exception\DeferredCaptureNotSupportedException;
use Thelia\Domain\Payment\Exception\InvalidPaymentAmountException;
use Thelia\Domain\Payment\Service\PaymentCaptureService;
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;
use Thelia\Domain\Payment\Service\PaymentTransactionTotalsReader;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransaction;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Model\OrderStatus;
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
        self::assertSame('CAP-1', $capture->getPspReference());
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

    public function testAModuleThatThrowsLeavesAFailedLineAndRethrows(): void
    {
        [$order] = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = new \RuntimeException('Connection timed out');

        try {
            $this->service->capture($order);
            self::fail('The module exception must reach the caller.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Connection timed out', $exception->getMessage());
        }

        $journal = OrderPaymentTransactionQuery::create()->findJournal($order->getId());
        self::assertCount(2, $journal);

        /** @var OrderPaymentTransaction $line */
        $line = $journal[0];
        self::assertTrue($line->isFailed());
        self::assertSame('exception', $line->getErrorCode());
        self::assertSame('Connection timed out', $line->getErrorMessage());
        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, $this->statusCodeOf($order));
    }

    public function testAModuleThatAnswersPendingLeavesTheLinePendingWithItsReference(): void
    {
        [$order] = $this->authorizedOrder(120);
        DeferredCapturePaymentModule::$nextCaptureAnswer = PaymentOperationResult::pending('CAP-ASYNC');

        $capture = $this->service->capture($order);

        self::assertTrue($capture->isPending());
        self::assertSame('CAP-ASYNC', $capture->getPspReference());
        self::assertSame('120.000000', $this->totals->forOrder($order->getId())->remainingToCapture, 'A pending capture counts for nothing yet.');
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
