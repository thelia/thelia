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
use Thelia\Domain\Payment\Exception\ConflictingPaymentReferenceException;
use Thelia\Domain\Payment\Exception\DeferredCaptureNotSupportedException;
use Thelia\Domain\Payment\Exception\DuplicateCaptureException;
use Thelia\Domain\Payment\Exception\InvalidPaymentAmountException;
use Thelia\Domain\Payment\Exception\MissingProviderReferenceException;
use Thelia\Domain\Payment\Exception\PaymentAnnouncementFailedException;
use Thelia\Domain\Payment\Exception\PaymentException;
use Thelia\Domain\Payment\Exception\PaymentJournalBusyException;
use Thelia\Domain\Payment\Exception\PaymentProviderUnreachableException;
use Thelia\Domain\Payment\Exception\PaymentRefusedException;
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
 * A module that refuses with a PaymentRefusedException leaves a failed line. Any other
 * exception — a timeout, a broken connection, an answer it could not read — leaves the
 * line pending: the call may have reached the provider, and only its notification can say
 * whether the money was taken. The caller then gets a PaymentProviderUnreachableException,
 * whose message says so without repeating what the module sent the provider.
 *
 * Once the provider has answered, nothing that follows reports the movement as failed:
 * a listener of the journal that breaks is logged, and a journal another worker holds
 * past the wait leaves the line pending, noting the answer, for the notification.
 */
final readonly class PaymentCaptureService
{
    private const ERROR_CODE_EXCEPTION = 'exception';

    private const ERROR_CODE_CONFLICTING_REFERENCE = 'conflicting_reference';

    private const ERROR_CODE_JOURNAL_BUSY = 'journal_busy';

    private const ERROR_CODE_MISSING_REFERENCE = 'missing_reference';

    private const UNKNOWN_OUTCOME_MESSAGE = 'The payment module could not get an answer from the provider: the outcome is known once the provider confirms it.';

    /**
     * How long, in seconds, the same capture asked again on the same order is taken for a
     * repetition rather than a new capture.
     */
    private const REPEAT_WINDOW_SECONDS = 60;

    public function __construct(
        private PaymentTransactionRecorder $recorder,
        private PaymentTransactionTotalsReader $totalsReader,
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

        $transaction = $this->keepingTheLine(fn (): OrderPaymentTransaction => $this->recorder->withOrderLock($order, function () use ($order, $amount, $currencyCode, $moduleCode): OrderPaymentTransaction {
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

        $result = $this->callModule(
            static fn (): PaymentOperationResult => $module->capture($order, PaymentAmount::toFloat((string) $transaction->getAmount()), $transaction),
            $transaction,
            $moduleCode,
        );

        return $this->conclude($transaction, $result, $moduleCode);
    }

    public function voidAuthorization(Order $order): OrderPaymentTransaction
    {
        $module = $this->captureModuleOf($order);
        $moduleCode = $module->getCode();

        $transaction = $this->keepingTheLine(fn (): OrderPaymentTransaction => $this->recorder->recordVoid(
            $order,
            null,
            PaymentTransactionState::PENDING,
            OrderPaymentTransactionQuery::create()->findLatestSucceededAuthorization((int) $order->getId()),
            $moduleCode,
        ));

        $result = $this->callModule(static fn (): PaymentOperationResult => $module->voidAuthorization($order, $transaction), $transaction, $moduleCode);

        return $this->conclude($transaction, $result, $moduleCode);
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
            $module = $order->getPaymentModuleInstance();
        } catch (TheliaProcessException) {
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
        } catch (PaymentRefusedException $refusal) {
            $this->keepTheOriginal($refusal, fn (): OrderPaymentTransaction => $this->recorder->settle(
                $transaction,
                PaymentTransactionState::FAILED,
                null,
                self::ERROR_CODE_EXCEPTION,
                $refusal->getMessage(),
                $moduleCode,
            ));

            throw $refusal;
        } catch (\Throwable $throwable) {
            // The technical message may carry what the module sent the provider — an URL
            // with its key, a piece of the answer: it goes to the log, not to the journal
            // every order reader sees.
            Tlog::getInstance()->error(\sprintf(
                'Payment module %s failed on transaction #%d (%s): %s',
                $moduleCode,
                (int) $transaction->getId(),
                $throwable::class,
                $throwable->getMessage(),
            ));

            $this->keepTheOriginal($throwable, fn (): OrderPaymentTransaction => $this->recorder->markOutcomeUnknown(
                $transaction,
                self::ERROR_CODE_EXCEPTION,
                self::UNKNOWN_OUTCOME_MESSAGE,
            ));

            throw new PaymentProviderUnreachableException(self::UNKNOWN_OUTCOME_MESSAGE, 0, $throwable);
        }
    }

    /**
     * Runs the bookkeeping of a failed call without letting its own failure hide the
     * exception the caller has to see.
     *
     * @param callable(): OrderPaymentTransaction $bookkeeping
     */
    private function keepTheOriginal(\Throwable $original, callable $bookkeeping): void
    {
        try {
            $bookkeeping();
        } catch (\Throwable $bookkeepingFailure) {
            Tlog::getInstance()->error(\sprintf(
                'The payment journal could not record the failure "%s": %s',
                $original->getMessage(),
                $bookkeepingFailure->getMessage(),
            ));
        }
    }

    /**
     * The line the journal wrote, even when a listener of its announcement failed: that
     * failure is logged by the recorder, and the line is what the rest of the call needs.
     *
     * @param callable(): OrderPaymentTransaction $write
     */
    private function keepingTheLine(callable $write): OrderPaymentTransaction
    {
        try {
            return $write();
        } catch (PaymentAnnouncementFailedException $announcementFailure) {
            return $announcementFailure->getTransaction();
        }
    }

    private function conclude(OrderPaymentTransaction $transaction, PaymentOperationResult $result, string $moduleCode): OrderPaymentTransaction
    {
        try {
            // The module hands the call to the provider and will learn the outcome from a
            // notification: the line stays pending, with the reference to find it by.
            if (!$result->state->isSettled()) {
                return null === $result->pspReference
                    ? $transaction
                    : $this->recorder->attachReference($transaction, $result->pspReference);
            }

            return $this->keepingTheLine(fn (): OrderPaymentTransaction => $this->recorder->settle(
                $transaction,
                $result->state,
                $result->pspReference,
                $result->errorCode,
                $result->errorMessage,
                $moduleCode,
            ));
        } catch (ConflictingPaymentReferenceException $conflict) {
            // The module answered with a reference another line already carries: whether
            // the provider took the money this time cannot be told. The line stays pending,
            // reserving what it asked for, and says why; the provider's notification, with
            // the reference it really gave, settles it.
            Tlog::getInstance()->error(\sprintf(
                'Payment module %s answered transaction #%d with the reference %s, already carried by another line.',
                $moduleCode,
                (int) $transaction->getId(),
                (string) $result->pspReference,
            ));

            $this->keepTheOriginal($conflict, fn (): OrderPaymentTransaction => $this->recorder->markOutcomeUnknown(
                $transaction,
                self::ERROR_CODE_CONFLICTING_REFERENCE,
                \sprintf('The payment module answered with the reference %s, which another movement of this order already carries: the outcome is known once the provider confirms it.', (string) $result->pspReference),
            ));

            throw $conflict;
        } catch (MissingProviderReferenceException $missing) {
            // The module says the money was taken but not under which reference: a line
            // counted as taken that no notification can find would be written twice. It
            // stays pending, reserving what it asked for, until the notification settles it.
            Tlog::getInstance()->error(\sprintf(
                'Payment module %s answered transaction #%d as succeeded without the provider reference.',
                $moduleCode,
                (int) $transaction->getId(),
            ));

            return $this->recorder->markOutcomeUnknown(
                $transaction,
                self::ERROR_CODE_MISSING_REFERENCE,
                'The payment module reported the movement as done without the provider reference: the outcome is recorded once the provider confirms it.',
            );
        } catch (PaymentJournalBusyException $busy) {
            // The provider answered, but another worker held the journal past the wait.
            // Reporting a failure would invite a second capture of money already taken:
            // the line stays pending, says what the provider answered, and the provider's
            // notification settles it.
            Tlog::getInstance()->error(\sprintf(
                'Payment module %s answered transaction #%d (%s, reference %s), but the journal was busy: %s',
                $moduleCode,
                (int) $transaction->getId(),
                $result->state->value,
                (string) $result->pspReference,
                $busy->getMessage(),
            ));

            return $this->recorder->markOutcomeUnknown(
                $transaction,
                self::ERROR_CODE_JOURNAL_BUSY,
                \sprintf('The provider answered %s under the reference %s while the journal was busy: the outcome is recorded once the provider confirms it.', $result->state->value, (string) ($result->pspReference ?? '-')),
            );
        }
    }
}
