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
use Thelia\Domain\Payment\Exception\ConflictingPaymentReferenceException;
use Thelia\Domain\Payment\Exception\InvalidProviderReferenceException;
use Thelia\Domain\Payment\Exception\MissingProviderReferenceException;
use Thelia\Domain\Payment\Exception\PaymentAnnouncementFailedException;
use Thelia\Domain\Payment\Exception\PaymentJournalBusyException;
use Thelia\Domain\Payment\Exception\PaymentProviderUnreachableException;
use Thelia\Domain\Payment\Exception\PaymentRefusedException;
use Thelia\Log\Tlog;
use Thelia\Model\OrderPaymentTransaction;

/**
 * Calls a payment module about a pending line of the journal and records its answer: the
 * capture, the release and the refund share it.
 *
 * A module that refuses with a PaymentRefusedException leaves a failed line. Any other
 * exception — a timeout, a broken connection, an answer it could not read — leaves the
 * line pending: the call may have reached the provider, and only its notification can say
 * whether the money moved. The caller then gets a PaymentProviderUnreachableException,
 * whose message says so without repeating what the module sent the provider.
 *
 * Once the provider has answered, nothing that follows reports the movement as failed:
 * a listener of the journal that breaks is logged, and a journal another worker holds
 * past the wait leaves the line pending, noting the answer, for the notification.
 */
final readonly class ProviderCallRunner
{
    private const ERROR_CODE_EXCEPTION = 'exception';

    private const ERROR_CODE_CONFLICTING_REFERENCE = 'conflicting_reference';

    private const ERROR_CODE_JOURNAL_BUSY = 'journal_busy';

    private const ERROR_CODE_MISSING_REFERENCE = 'missing_reference';

    private const ERROR_CODE_INVALID_REFERENCE = 'invalid_reference';

    private const UNKNOWN_OUTCOME_MESSAGE = 'The payment module could not get an answer from the provider: the outcome is known once the provider confirms it.';

    public function __construct(
        private PaymentTransactionRecorder $recorder,
    ) {
    }

    /**
     * Calls the module about the pending line and records what it answered.
     *
     * @param callable(): PaymentOperationResult $call
     */
    public function run(callable $call, OrderPaymentTransaction $transaction, string $moduleCode): OrderPaymentTransaction
    {
        return $this->conclude($transaction, $this->callModule($call, $transaction, $moduleCode), $moduleCode);
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
    public function keepingTheLine(callable $write): OrderPaymentTransaction
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
        } catch (InvalidProviderReferenceException $invalid) {
            // A refusal took nothing: it stands without the reference the journal cannot
            // hold, which nothing will ever need to find this line by.
            if (PaymentTransactionState::FAILED === $result->state) {
                return $this->keepingTheLine(fn (): OrderPaymentTransaction => $this->recorder->settle(
                    $transaction,
                    PaymentTransactionState::FAILED,
                    null,
                    $result->errorCode,
                    $result->errorMessage,
                    $moduleCode,
                ));
            }

            // Anything else cannot be written as it came: the line stays pending, reserving
            // what it asked for, and says why, for the merchant to settle it by hand.
            Tlog::getInstance()->error(\sprintf(
                'Payment module %s answered transaction #%d with a reference the journal cannot hold: %s',
                $moduleCode,
                (int) $transaction->getId(),
                $invalid->getMessage(),
            ));

            return $this->recorder->markOutcomeUnknown(
                $transaction,
                self::ERROR_CODE_INVALID_REFERENCE,
                'The payment module answered with a provider reference longer than the journal holds: read the outcome at the provider and record it by hand.',
            );
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
