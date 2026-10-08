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

namespace Thelia\Domain\Payment\Service;

use Thelia\Domain\Payment\DTO\PaymentOperationResult;
use Thelia\Domain\Payment\Enum\PaymentTransactionState;
use Thelia\Domain\Payment\Exception\CaptureExceedsAuthorizationException;
use Thelia\Domain\Payment\Exception\DeferredCaptureNotSupportedException;
use Thelia\Domain\Payment\Exception\InvalidPaymentAmountException;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransaction;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Module\PaymentModuleWithCaptureInterface;

/**
 * Takes, or releases, what an order's authorization still holds, through the module
 * that holds it.
 *
 * The line is written as pending before the provider is called and settled with its
 * answer, so a call that times out or throws leaves a failed line rather than nothing.
 * The amount is checked here, against the journal, before anything leaves the shop:
 * a screen that lets a larger figure through is refused all the same.
 */
final readonly class PaymentCaptureService
{
    private const ERROR_CODE_EXCEPTION = 'exception';

    public function __construct(
        private PaymentTransactionRecorder $recorder,
        private PaymentTransactionTotalsReader $totalsReader,
    ) {
    }

    /**
     * @param float|null $amount what to take; null takes everything still held
     */
    public function capture(Order $order, ?float $amount = null): OrderPaymentTransaction
    {
        $module = $this->captureModuleOf($order);
        $totals = $this->totalsReader->forOrder((int) $order->getId());

        if (!$totals->hasSomethingLeftToCapture()) {
            throw new InvalidPaymentAmountException(\sprintf('Order %s: no authorization holds anything to capture.', (string) $order->getRef()));
        }

        $amount ??= PaymentAmount::toFloat($totals->remainingToCapture);

        if (!PaymentAmount::isPositive($amount)) {
            throw new InvalidPaymentAmountException(\sprintf('Order %s: a capture needs a positive amount, %s given.', (string) $order->getRef(), PaymentAmount::normalize($amount)));
        }

        if (!$totals->allows(PaymentAmount::normalize($amount))) {
            throw new CaptureExceedsAuthorizationException((string) $order->getRef(), PaymentAmount::normalize($amount), $totals->remainingToCapture);
        }

        $moduleCode = $module->getCode();
        $authorization = OrderPaymentTransactionQuery::create()->findLatestSucceededAuthorization((int) $order->getId());

        $transaction = $this->recorder->recordCapture(
            $order,
            $amount,
            null,
            PaymentTransactionState::PENDING,
            $authorization,
            $moduleCode,
        );

        $result = $this->callModule(static fn (): PaymentOperationResult => $module->capture($order, $amount, $transaction), $transaction, $moduleCode);

        return $this->settle($transaction, $result, $moduleCode);
    }

    public function voidAuthorization(Order $order): OrderPaymentTransaction
    {
        $module = $this->captureModuleOf($order);
        $moduleCode = $module->getCode();
        $authorization = OrderPaymentTransactionQuery::create()->findLatestSucceededAuthorization((int) $order->getId());

        $transaction = $this->recorder->recordVoid(
            $order,
            null,
            PaymentTransactionState::PENDING,
            $authorization,
            $moduleCode,
        );

        $result = $this->callModule(static fn (): PaymentOperationResult => $module->voidAuthorization($order, $transaction), $transaction, $moduleCode);

        return $this->settle($transaction, $result, $moduleCode);
    }

    public function supportsCapture(Order $order): bool
    {
        try {
            $this->captureModuleOf($order);
        } catch (DeferredCaptureNotSupportedException) {
            return false;
        }

        return true;
    }

    private function captureModuleOf(Order $order): PaymentModuleWithCaptureInterface
    {
        try {
            $module = $order->getPaymentModuleInstance();
        } catch (\Throwable) {
            throw new DeferredCaptureNotSupportedException((string) $order->getRef(), $order->getPaymentModuleTitle());
        }

        if (!$module instanceof PaymentModuleWithCaptureInterface || !$module->supportsDeferredCapture()) {
            throw new DeferredCaptureNotSupportedException((string) $order->getRef(), $module->getCode());
        }

        return $module;
    }

    /**
     * @param callable(): PaymentOperationResult $call
     */
    private function callModule(callable $call, OrderPaymentTransaction $transaction, string $moduleCode): PaymentOperationResult
    {
        try {
            return $call();
        } catch (\Throwable $throwable) {
            $this->recorder->settle(
                $transaction,
                PaymentTransactionState::FAILED,
                null,
                self::ERROR_CODE_EXCEPTION,
                $throwable->getMessage(),
                $moduleCode,
            );

            throw $throwable;
        }
    }

    private function settle(OrderPaymentTransaction $transaction, PaymentOperationResult $result, string $moduleCode): OrderPaymentTransaction
    {
        // The module hands the call to the provider and will learn the outcome from a
        // notification: the line stays pending, with the reference to find it by.
        if (!$result->state->isSettled()) {
            if (null !== $result->pspReference) {
                $transaction->setPspReference($result->pspReference)->save();
            }

            return $transaction;
        }

        return $this->recorder->settle(
            $transaction,
            $result->state,
            $result->pspReference,
            $result->errorCode,
            $result->errorMessage,
            $moduleCode,
        );
    }
}
