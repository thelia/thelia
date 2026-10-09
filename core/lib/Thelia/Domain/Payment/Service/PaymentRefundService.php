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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderRefundedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\DTO\PaymentOperationResult;
use Thelia\Domain\Payment\Enum\PaymentTransactionState;
use Thelia\Domain\Payment\Enum\RefundReason;
use Thelia\Domain\Payment\Exception\DuplicateRefundException;
use Thelia\Domain\Payment\Exception\InvalidPaymentAmountException;
use Thelia\Domain\Payment\Exception\PaymentException;
use Thelia\Domain\Payment\Exception\RefundNotSupportedException;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransaction;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Module\PaymentModuleWithRefundInterface;

/**
 * Gives back all or part of what an order's payment collected: through the module that took
 * it, or recorded by hand when the money left another way (a bank transfer, a cheque).
 *
 * What can be given back is read in the journal — what was captured, less what was refunded
 * or is waiting for the provider's answer — never in the order total, which a partly
 * captured order does not match. The amount is checked and the line written under the lock
 * of the order's journal; the same amount asked again within a minute is refused as a
 * repetition. The provider is called outside the lock, and its answer recorded as for a
 * capture, see ProviderCallRunner.
 *
 * The service never moves the order: once nothing collected is left, the journal does,
 * through the transition graph. ORDER_REFUNDED is sent when money was given back.
 */
final readonly class PaymentRefundService
{
    /** The error code a refund recorded by hand carries: no provider was called. */
    public const ERROR_CODE_OFFLINE = 'offline';

    private const OFFLINE_DEFAULT_NOTE = 'Refunded outside the payment provider.';

    private const COMMENT_MAX_LENGTH = 255;

    /**
     * How long, in seconds, the same refund asked again on the same order is taken for a
     * repetition rather than a new refund.
     */
    private const REPEAT_WINDOW_SECONDS = 60;

    public function __construct(
        private PaymentTransactionRecorder $recorder,
        private PaymentTransactionTotalsReader $totalsReader,
        private ProviderCallRunner $runner,
        private EventDispatcherInterface $eventDispatcher,
        private PaymentModuleLocator $moduleLocator,
    ) {
    }

    /**
     * @param float|null $amount what to give back, in the order currency; null gives back everything still refundable
     *
     * @throws PaymentException when the shop or the provider refuses the refund, or the outcome is unknown (PaymentProviderUnreachableException)
     */
    public function refund(Order $order, ?float $amount, RefundReason $reason, ?string $comment = null): OrderPaymentTransaction
    {
        $module = $this->refundModuleOf($order);
        $moduleCode = $module->getCode();
        $comment = self::cleanComment($comment);

        $transaction = $this->runner->keepingTheLine(fn (): OrderPaymentTransaction => $this->recorder->withOrderLock(
            $order,
            fn (): OrderPaymentTransaction => $this->recorder->recordRefund(
                $order,
                $this->checkedAmount($order, $amount),
                null,
                PaymentTransactionState::PENDING,
                OrderPaymentTransactionQuery::create()->findLatestSucceededCapture((int) $order->getId()),
                $moduleCode,
            ),
        ));

        $transaction = $this->runner->run(
            static fn (): PaymentOperationResult => $module->refund($order, PaymentAmount::toFloat((string) $transaction->getAmount()), $transaction, $reason, $comment),
            $transaction,
            $moduleCode,
        );

        if ($transaction->isSucceeded()) {
            $this->announce($order, $transaction, $reason, $comment, false);
        }

        return $transaction;
    }

    /**
     * Records money given back outside the payment provider. Any order can have one, whatever
     * its module: the line says so, with the merchant's comment.
     */
    public function recordOfflineRefund(Order $order, ?float $amount, RefundReason $reason, ?string $comment = null): OrderPaymentTransaction
    {
        $comment = self::cleanComment($comment);

        $transaction = $this->runner->keepingTheLine(fn (): OrderPaymentTransaction => $this->recorder->withOrderLock(
            $order,
            fn (): OrderPaymentTransaction => $this->recorder->recordRefund(
                $order,
                $this->checkedAmount($order, $amount),
                // A reference of its own, the journal asking one of every outcome: no provider gave any.
                'offline-'.bin2hex(random_bytes(8)),
                PaymentTransactionState::SUCCEEDED,
                OrderPaymentTransactionQuery::create()->findLatestSucceededCapture((int) $order->getId()),
                null,
                self::ERROR_CODE_OFFLINE,
                $comment ?? self::OFFLINE_DEFAULT_NOTE,
            ),
        ));

        $this->announce($order, $transaction, $reason, $comment, true);

        return $transaction;
    }

    /**
     * Whether the payment module of the order refunds through its provider. A module that
     * cannot even be instantiated answers no, so the order sheet still renders.
     */
    public function supportsRefund(Order $order): bool
    {
        try {
            $this->refundModuleOf($order);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * The amount, read under the lock: positive, a whole number of the currency's smallest
     * coin, no more than what is left to give back, and not the one just asked.
     */
    private function checkedAmount(Order $order, ?float $amount): string
    {
        $currencyCode = $order->getCurrency()->getCode();
        $refundable = $this->totalsReader->forOrder((int) $order->getId())->refundable();

        if (!PaymentAmount::isPositive($refundable)) {
            throw new InvalidPaymentAmountException(\sprintf('Order %s: nothing collected is left to refund.', (string) $order->getRef()));
        }

        $amount ??= PaymentAmount::toFloat(CurrencyMinorUnit::floor($refundable, $currencyCode));

        if (!PaymentAmount::isPositive($amount)) {
            throw new InvalidPaymentAmountException(\sprintf('Order %s: a refund needs a positive amount, %s given.', (string) $order->getRef(), PaymentAmount::forMessage($amount)));
        }

        if (!CurrencyMinorUnit::fits($amount, $currencyCode)) {
            throw new InvalidPaymentAmountException(\sprintf('Order %s: %s is not an amount that can be refunded in %s.', (string) $order->getRef(), PaymentAmount::forMessage($amount), (string) $currencyCode));
        }

        $normalizedAmount = PaymentAmount::normalize($amount);

        if (PaymentAmount::compare($normalizedAmount, $refundable) > 0) {
            throw new InvalidPaymentAmountException(\sprintf('Order %s: a refund of %s exceeds the %s collected and not yet given back.', (string) $order->getRef(), PaymentAmount::forMessage($normalizedAmount), PaymentAmount::forMessage($refundable)));
        }

        $repeated = OrderPaymentTransactionQuery::create()->findRecentRefundOf(
            (int) $order->getId(),
            $normalizedAmount,
            new \DateTimeImmutable('-'.self::REPEAT_WINDOW_SECONDS.' seconds'),
        );

        if (null !== $repeated) {
            throw new DuplicateRefundException(\sprintf('Order %s: the same amount was refunded a moment ago. Read the payment journal before asking again.', (string) $order->getRef()));
        }

        return $normalizedAmount;
    }

    private function refundModuleOf(Order $order): PaymentModuleWithRefundInterface
    {
        try {
            $module = $this->moduleLocator->paymentModuleOf($order);
        } catch (TheliaProcessException) {
            throw new RefundNotSupportedException((string) $order->getRef(), $order->getPaymentModuleTitle());
        }

        if (!$module instanceof PaymentModuleWithRefundInterface || !$module->supportsRefund()) {
            throw new RefundNotSupportedException((string) $order->getRef(), $module->getCode());
        }

        return $module;
    }

    private function announce(Order $order, OrderPaymentTransaction $transaction, RefundReason $reason, ?string $comment, bool $offline): void
    {
        $this->eventDispatcher->dispatch(new OrderRefundedEvent($order, $transaction, $reason, $comment, $offline), TheliaEvents::ORDER_REFUNDED);
    }

    /**
     * Typed by an administrator and sent to the provider: no control character, at most 255
     * characters, nothing for an empty text.
     */
    private static function cleanComment(?string $comment): ?string
    {
        if (null === $comment) {
            return null;
        }

        $comment = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $comment));

        return '' === $comment ? null : mb_substr($comment, 0, self::COMMENT_MAX_LENGTH);
    }
}
