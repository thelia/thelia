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
use Thelia\Domain\Payment\Exception\DuplicateCaptureException;
use Thelia\Domain\Payment\Exception\InvalidPaymentAmountException;
use Thelia\Domain\Payment\Exception\PaymentException;
use Thelia\Domain\Payment\Exception\PaymentProviderUnreachableException;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransaction;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Module\PaymentModuleWithCaptureInterface;

/**
 * Takes, or releases, what an order's authorization still holds, through the module
 * that holds it. The back office, the admin API and a module's own automation all come
 * through here, so they share one set of guards.
 *
 * The amount is checked against the journal, in the currency's smallest coin, and the
 * line written as pending, under the lock of the order's journal: a second request can
 * only see that first line, which already reserves what it asked for. The same amount
 * asked again within a minute is refused as a repetition. The provider is called outside
 * the lock and the line settled with its answer.
 *
 * The module is called, and its answer recorded, by ProviderCallRunner.
 */
final readonly class PaymentCaptureService
{
    /**
     * How long, in seconds, the same capture asked again on the same order is taken for a
     * repetition rather than a new capture.
     */
    private const REPEAT_WINDOW_SECONDS = 60;

    public function __construct(
        private PaymentTransactionRecorder $recorder,
        private PaymentTransactionTotalsReader $totalsReader,
        private ProviderCallRunner $runner,
        private PaymentModuleLocator $moduleLocator,
    ) {
    }

    /**
     * @param float|null $amount what to take, in the order currency; null takes everything still held
     *
     * @throws PaymentException when the shop or the provider refuses the capture, or the outcome is unknown (PaymentProviderUnreachableException)
     */
    public function capture(Order $order, ?float $amount = null): OrderPaymentTransaction
    {
        $module = $this->captureModuleOf($order);
        $moduleCode = $module->getCode();
        $currencyCode = $order->getCurrency()->getCode();

        if (null !== $amount) {
            $this->assertCapturable($order, $amount, $currencyCode);
        }

        $transaction = $this->runner->keepingTheLine(fn (): OrderPaymentTransaction => $this->recorder->withOrderLock($order, function () use ($order, $amount, $currencyCode, $moduleCode): OrderPaymentTransaction {
            $totals = $this->totalsReader->forOrder((int) $order->getId());

            if (!$totals->hasSomethingLeftToCapture()) {
                throw new InvalidPaymentAmountException($totals->hasPendingCapture() ? \sprintf('Order %s: a capture is waiting for the provider\'s answer; nothing else is left to capture.', (string) $order->getRef()) : \sprintf('Order %s: no authorization holds anything to capture.', (string) $order->getRef()));
            }

            $amount ??= PaymentAmount::toFloat(CurrencyMinorUnit::floor($totals->remainingToCapture, $currencyCode));
            $this->assertCapturable($order, $amount, $currencyCode);
            $normalizedAmount = PaymentAmount::normalize($amount);

            if (!$totals->allows($normalizedAmount)) {
                throw new CaptureExceedsAuthorizationException((string) $order->getRef(), $normalizedAmount, $totals->remainingToCapture);
            }

            $repeated = OrderPaymentTransactionQuery::create()->findRecentCaptureOf(
                (int) $order->getId(),
                $normalizedAmount,
                new \DateTimeImmutable('-'.self::REPEAT_WINDOW_SECONDS.' seconds'),
            );

            if (null !== $repeated) {
                throw new DuplicateCaptureException(\sprintf('Order %s: the same amount was captured a moment ago. Read the payment journal before asking again.', (string) $order->getRef()));
            }

            return $this->recorder->recordCapture(
                $order,
                $normalizedAmount,
                null,
                PaymentTransactionState::PENDING,
                OrderPaymentTransactionQuery::create()->findLatestSucceededAuthorization((int) $order->getId()),
                $moduleCode,
            );
        }));

        return $this->runner->run(
            static fn (): PaymentOperationResult => $module->capture($order, PaymentAmount::toFloat((string) $transaction->getAmount()), $transaction),
            $transaction,
            $moduleCode,
        );
    }

    public function voidAuthorization(Order $order): OrderPaymentTransaction
    {
        $module = $this->captureModuleOf($order);
        $moduleCode = $module->getCode();

        $transaction = $this->runner->keepingTheLine(fn (): OrderPaymentTransaction => $this->recorder->recordVoid(
            $order,
            null,
            PaymentTransactionState::PENDING,
            OrderPaymentTransactionQuery::create()->findLatestSucceededAuthorization((int) $order->getId()),
            $moduleCode,
        ));

        return $this->runner->run(static fn (): PaymentOperationResult => $module->voidAuthorization($order, $transaction), $transaction, $moduleCode);
    }

    /**
     * Whether the payment module of the order can capture by hand. A module that cannot
     * even be instantiated answers no here, so the order sheet still renders, and is
     * logged; asked to capture, it fails loudly.
     */
    public function supportsCapture(Order $order): bool
    {
        try {
            $this->captureModuleOf($order);
        } catch (DeferredCaptureNotSupportedException) {
            return false;
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->warning(\sprintf(
                'The payment module of order %s cannot be instantiated: %s',
                (string) $order->getRef(),
                $throwable->getMessage(),
            ));

            return false;
        }

        return true;
    }

    private function assertCapturable(Order $order, float $amount, ?string $currencyCode): void
    {
        if (!PaymentAmount::isPositive($amount)) {
            throw new InvalidPaymentAmountException(\sprintf('Order %s: a capture needs a positive amount, %s given.', (string) $order->getRef(), PaymentAmount::forMessage($amount)));
        }

        if (!CurrencyMinorUnit::fits($amount, $currencyCode)) {
            throw new InvalidPaymentAmountException(\sprintf('Order %s: %s is not an amount the provider can take in %s.', (string) $order->getRef(), PaymentAmount::forMessage($amount), (string) $currencyCode));
        }
    }

    /**
     * The payment module of the order, when it captures by hand. A module that is gone is
     * one that cannot; a module that is installed and fails to load is a fault, and is
     * left to surface.
     */
    private function captureModuleOf(Order $order): PaymentModuleWithCaptureInterface
    {
        try {
            $module = $this->moduleLocator->paymentModuleOf($order);
        } catch (TheliaProcessException) {
            throw new DeferredCaptureNotSupportedException((string) $order->getRef(), $this->moduleLocator->nameOf($order));
        }

        if (!$module instanceof PaymentModuleWithCaptureInterface || !$module->supportsDeferredCapture()) {
            throw new DeferredCaptureNotSupportedException((string) $order->getRef(), $module->getCode());
        }

        return $module;
    }
}
