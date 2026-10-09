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

use Propel\Runtime\Exception\PropelException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderPaymentTransactionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\DTO\OrderHistoryActor;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Service\OrderHistoryActorResolver;
use Thelia\Domain\Payment\Enum\PaymentTransactionState;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Domain\Payment\Exception\CaptureExceedsAuthorizationException;
use Thelia\Domain\Payment\Exception\ConflictingPaymentReferenceException;
use Thelia\Domain\Payment\Exception\InvalidPaymentAmountException;
use Thelia\Domain\Payment\Exception\MissingProviderReferenceException;
use Thelia\Domain\Payment\Exception\PaymentException;
use Thelia\Model\Map\OrderPaymentTransactionTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransaction;
use Thelia\Model\OrderPaymentTransactionQuery;

/**
 * Writes the payment journal of an order.
 *
 * One method per movement rather than a generic save: each one knows what it has to
 * check. A capture never exceeds what the authorization still holds, a refund never
 * exceeds what was taken, once the movements still waiting for the provider's answer are
 * counted as done. Every check runs with the write that depends on it under the lock of
 * the order's journal, so two workers can never both pass it.
 *
 * A movement reported again is answered with the line already written, and the
 * ORDER_PAYMENT_TRANSACTION_RECORDED event is raised again for it, so a notification that
 * failed half way — the line written, the order not moved — heals when the provider
 * replays it. The listeners of that event must therefore stand being called twice for
 * the same line. A line with a provider reference is found by it; a settled line without
 * one by its type, outcome and amount within the last minute. A reference the journal
 * does not know settles the one pending line of that movement and amount still waiting
 * for its reference, when there is exactly one. A reference the journal
 * holds with another outcome or another amount is refused: a new attempt carries a new
 * reference.
 *
 * A failure here is not swallowed, unlike an order history entry: a payment whose trace
 * cannot be written is a payment the merchant cannot account for.
 *
 * Not to be called inside a database transaction the caller opened: the lock is released
 * before that transaction commits, and the event is raised for rows its rollback erases.
 */
final readonly class PaymentTransactionRecorder
{
    /**
     * How long, in seconds, a settled line without a provider reference stands for the
     * same movement reported again.
     */
    private const REPLAY_WINDOW_SECONDS = 60;

    private const DUPLICATE_KEY_SQLSTATE = '23000';

    public function __construct(
        private OrderHistoryActorResolver $actorResolver,
        private PaymentTransactionTotalsReader $totalsReader,
        private EventDispatcherInterface $eventDispatcher,
        private PaymentJournalLock $journalLock,
    ) {
    }

    /**
     * @param string $pspReference the reference the provider gave the authorization; required, it is what tells a replayed notification from a second authorization
     */
    public function recordAuthorization(
        Order $order,
        float|string $amount,
        ?string $pspReference = null,
        PaymentTransactionState $state = PaymentTransactionState::SUCCEEDED,
        ?string $moduleCode = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): OrderPaymentTransaction {
        if (!PaymentAmount::isPositive($amount)) {
            throw new InvalidPaymentAmountException(\sprintf('Order %s: an authorization needs a positive amount, %s given.', (string) $order->getRef(), PaymentAmount::forMessage($amount)));
        }

        if (null === $this->cleanReference($pspReference)) {
            throw new MissingProviderReferenceException(\sprintf('Order %s: an authorization is recorded with the reference the provider gave it.', (string) $order->getRef()));
        }

        return $this->record($order, PaymentTransactionType::AUTHORIZATION, $amount, $state, $pspReference, null, $moduleCode, $errorCode, $errorMessage);
    }

    public function recordCapture(
        Order $order,
        float|string $amount,
        ?string $pspReference = null,
        PaymentTransactionState $state = PaymentTransactionState::SUCCEEDED,
        ?OrderPaymentTransaction $authorization = null,
        ?string $moduleCode = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): OrderPaymentTransaction {
        // Zero is a capture: an order that costs nothing is paid without taking anything.
        if (PaymentAmount::compare($amount, 0) < 0) {
            throw new InvalidPaymentAmountException(\sprintf('Order %s: a capture cannot be negative, %s given.', (string) $order->getRef(), PaymentAmount::forMessage($amount)));
        }

        $guard = PaymentTransactionState::FAILED === $state
            ? null
            : fn (): null => $this->assertCaptureFitsAuthorization($order, $amount);

        return $this->record($order, PaymentTransactionType::CAPTURE, $amount, $state, $pspReference, $authorization, $moduleCode, $errorCode, $errorMessage, $guard);
    }

    public function recordRefund(
        Order $order,
        float|string $amount,
        ?string $pspReference = null,
        PaymentTransactionState $state = PaymentTransactionState::SUCCEEDED,
        ?OrderPaymentTransaction $capture = null,
        ?string $moduleCode = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): OrderPaymentTransaction {
        if (!PaymentAmount::isPositive($amount)) {
            throw new InvalidPaymentAmountException(\sprintf('Order %s: a refund needs a positive amount, %s given.', (string) $order->getRef(), PaymentAmount::forMessage($amount)));
        }

        $guard = PaymentTransactionState::FAILED === $state
            ? null
            : function () use ($order, $amount): null {
                $refundable = $this->totalsReader->forOrder((int) $order->getId())->refundable();

                if (PaymentAmount::compare($amount, $refundable) > 0) {
                    throw new InvalidPaymentAmountException(\sprintf('Order %s: a refund of %s exceeds the %s taken and not yet given back.', (string) $order->getRef(), PaymentAmount::forMessage($amount), PaymentAmount::forMessage($refundable)));
                }

                return null;
            };

        return $this->record($order, PaymentTransactionType::REFUND, $amount, $state, $pspReference, $capture, $moduleCode, $errorCode, $errorMessage, $guard);
    }

    /**
     * Releases what the authorization still holds once the captures awaiting their
     * answer are set aside. The amount is read under the lock, so the totals read zero
     * left to capture afterwards.
     *
     * A void the journal already knows is answered with its own amount, not with what is
     * left once it is counted: by its reference, or, for a reference it does not know yet,
     * as the one pending void still waiting for it — a release the shop asked for whose
     * answer never came back.
     */
    public function recordVoid(
        Order $order,
        ?string $pspReference = null,
        PaymentTransactionState $state = PaymentTransactionState::SUCCEEDED,
        ?OrderPaymentTransaction $authorization = null,
        ?string $moduleCode = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): OrderPaymentTransaction {
        return $this->journalLock->withOrder((int) $order->getId(), function () use ($order, $pspReference, $state, $authorization, $moduleCode, $errorCode, $errorMessage): OrderPaymentTransaction {
            $orderId = (int) $order->getId();
            $reference = $this->cleanReference($pspReference);

            $known = null === $reference
                ? null
                : OrderPaymentTransactionQuery::create()->findByReference($orderId, PaymentTransactionType::VOID, $reference)
                    ?? ($state->isSettled() ? $this->lineWaitingForItsReference($orderId, PaymentTransactionType::VOID) : null);

            if (null !== $known) {
                return $this->record($order, PaymentTransactionType::VOID, (string) $known->getAmount(), $state, $reference, $authorization, $moduleCode, $errorCode, $errorMessage);
            }

            $remaining = $this->totalsReader->forOrder($orderId)->remainingToCapture;

            if (PaymentTransactionState::FAILED !== $state && !PaymentAmount::isPositive($remaining)) {
                throw new InvalidPaymentAmountException(\sprintf('Order %s: no authorization holds anything to release.', (string) $order->getRef()));
            }

            return $this->record($order, PaymentTransactionType::VOID, $remaining, $state, $reference, $authorization, $moduleCode, $errorCode, $errorMessage);
        });
    }

    /**
     * The capture line of an order paid by a module that keeps no journal: written once,
     * when the order first reaches a paid status, and only when the journal holds no
     * authorization and no capture yet, whatever wrote them.
     *
     * No ceiling applies: the order was paid by whoever marked it so, and the line says
     * so. Nothing is written when the journal already tells the story, which keeps a
     * capture nobody took off an order whose authorization is still open.
     */
    public function recordImmediateCaptureIfNone(
        Order $order,
        float|string $amount,
        ?string $pspReference = null,
        ?string $moduleCode = null,
    ): ?OrderPaymentTransaction {
        $orderId = (int) $order->getId();

        return $this->journalLock->withOrder($orderId, function () use ($order, $orderId, $amount, $pspReference, $moduleCode): ?OrderPaymentTransaction {
            if (OrderPaymentTransactionQuery::create()->holdsMovement($orderId, [PaymentTransactionType::AUTHORIZATION, PaymentTransactionType::CAPTURE])) {
                return null;
            }

            return $this->record($order, PaymentTransactionType::CAPTURE, $amount, PaymentTransactionState::SUCCEEDED, $pspReference, null, $moduleCode, null, null);
        });
    }

    /**
     * Gives a pending line its outcome. The only change a line ever receives, with the
     * reference the provider gave it when the line was written without one.
     */
    public function settle(
        OrderPaymentTransaction $transaction,
        PaymentTransactionState $state,
        ?string $pspReference = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?string $moduleCode = null,
    ): OrderPaymentTransaction {
        if (!$state->isSettled()) {
            throw new \InvalidArgumentException('A pending line is settled to succeeded or failed, not left pending.');
        }

        return $this->journalLock->withOrder((int) $transaction->getOrderId(), function () use ($transaction, $state, $pspReference, $errorCode, $errorMessage, $moduleCode): OrderPaymentTransaction {
            $transaction->reload();

            if (!$transaction->isPending()) {
                throw new PaymentException(\sprintf('Payment transaction #%d is already %s and cannot be settled again.', (int) $transaction->getId(), (string) $transaction->getState()));
            }

            $pspReference = $this->cleanReference($pspReference);

            $transaction->setState($state->value);

            if (null !== $pspReference) {
                $transaction->setPspReference($pspReference);
            }

            $transaction
                ->setErrorCode($errorCode)
                ->setErrorMessage($errorMessage);

            // A reference another line carries is refused here, the line left pending as the
            // database holds it, for the caller to note why and the notification to settle.
            $this->save($transaction);

            $this->announce($transaction->getOrder(), $transaction, $moduleCode);

            return $transaction;
        });
    }

    /**
     * Gives a pending line the reference the provider answered with, so that the
     * notification bringing its outcome finds it.
     */
    public function attachReference(OrderPaymentTransaction $transaction, string $pspReference): OrderPaymentTransaction
    {
        $pspReference = $this->cleanReference($pspReference);

        if (null === $pspReference || !$transaction->isPending()) {
            return $transaction;
        }

        return $this->journalLock->withOrder((int) $transaction->getOrderId(), function () use ($transaction, $pspReference): OrderPaymentTransaction {
            $transaction->setPspReference($pspReference);
            $this->save($transaction);

            return $transaction;
        });
    }

    /**
     * Notes on a pending line why its outcome is not known — a call that threw or timed
     * out may have reached the provider. The line stays pending, keeping what it asked
     * for out of reach of another movement, until the provider's notification settles it.
     */
    public function markOutcomeUnknown(OrderPaymentTransaction $transaction, string $errorCode, string $errorMessage): OrderPaymentTransaction
    {
        $line = $this->freshCopyOf($transaction);

        if (!$line->isPending()) {
            return $line;
        }

        $line
            ->setErrorCode($errorCode)
            ->setErrorMessage($errorMessage);
        $this->save($line);

        return $line;
    }

    /**
     * Runs $work with the journal of the order locked, so that what it reads still holds
     * when it writes.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function withOrderLock(Order $order, callable $work): mixed
    {
        return $this->journalLock->withOrder((int) $order->getId(), $work);
    }

    /**
     * @param (callable(): null)|null $guard the check that has to hold when the line is written, run under the lock
     */
    private function record(
        Order $order,
        PaymentTransactionType $type,
        float|string $amount,
        PaymentTransactionState $state,
        ?string $pspReference,
        ?OrderPaymentTransaction $parent,
        ?string $moduleCode,
        ?string $errorCode,
        ?string $errorMessage,
        ?callable $guard = null,
    ): OrderPaymentTransaction {
        $orderId = (int) $order->getId();
        $pspReference = $this->cleanReference($pspReference);
        $amount = PaymentAmount::normalize($amount);

        if (null !== $parent && (int) $parent->getOrderId() !== $orderId) {
            throw new \InvalidArgumentException(\sprintf('Payment transaction #%d belongs to another order than %s.', (int) $parent->getId(), (string) $order->getRef()));
        }

        return $this->journalLock->withOrder($orderId, function () use ($order, $orderId, $type, $amount, $state, $pspReference, $parent, $moduleCode, $errorCode, $errorMessage, $guard): OrderPaymentTransaction {
            $existing = null !== $pspReference
                ? OrderPaymentTransactionQuery::create()->findByReference($orderId, $type, $pspReference)
                : $this->replayedReferenceLessLine($orderId, $type, $state, $amount);

            if (null !== $existing) {
                return $this->answerReplay($order, $existing, $amount, $state, $pspReference, $errorCode, $errorMessage, $moduleCode);
            }

            $waiting = null !== $pspReference && $state->isSettled()
                ? $this->lineWaitingForItsReference($orderId, $type, $amount)
                : null;

            if (null !== $waiting) {
                return $this->settle($waiting, $state, $pspReference, $errorCode, $errorMessage, $moduleCode);
            }

            if (null !== $guard) {
                $guard();
            }

            $actor = $this->actorOf($moduleCode);

            $transaction = new OrderPaymentTransaction();
            $transaction
                ->setOrderId($orderId)
                ->setType($type->value)
                ->setState($state->value)
                ->setAmount($amount)
                ->setCurrencyId((int) $order->getCurrencyId())
                ->setPspReference($pspReference)
                ->setParentId($parent?->getId())
                ->setPaymentModuleId($order->getPaymentModuleId())
                ->setActorType($actor->actorType->value)
                ->setActorLabel($actor->label)
                ->setAdminId($actor->adminId)
                ->setErrorCode($errorCode)
                ->setErrorMessage($errorMessage);

            $this->save($transaction);
            $this->announce($order, $transaction, $moduleCode);

            return $transaction;
        });
    }

    /**
     * The same movement reported again: a pending line gets the outcome the report brings,
     * a line with the same outcome and amount is answered as is and announced again, so
     * that whatever failed after it was first written runs this time. Anything else is a
     * reference the provider reused for another movement, and is refused.
     */
    private function answerReplay(
        Order $order,
        OrderPaymentTransaction $existing,
        string $amount,
        PaymentTransactionState $state,
        ?string $pspReference,
        ?string $errorCode,
        ?string $errorMessage,
        ?string $moduleCode,
    ): OrderPaymentTransaction {
        $sameAmount = 0 === PaymentAmount::compare($amount, (string) $existing->getAmount());

        if ($existing->isPending() && $state->isSettled() && $sameAmount) {
            return $this->settle($existing, $state, $pspReference, $errorCode, $errorMessage, $moduleCode);
        }

        if ($sameAmount && ($existing->getState() === $state->value || PaymentTransactionState::PENDING === $state)) {
            $this->announce($order, $existing, $moduleCode);

            return $existing;
        }

        throw new ConflictingPaymentReferenceException(\sprintf('Order %s: the %s %s is already recorded as %s of %s; a new attempt is reported with a new reference.', (string) $order->getRef(), (string) $existing->getType(), (string) ($existing->getPspReference() ?? '#'.$existing->getId()), (string) $existing->getState(), PaymentAmount::forMessage(PaymentAmount::forMessage((string) $existing->getAmount()))));
    }

    /**
     * A settled line without a provider reference stands for the same movement reported
     * within the last minute with the same outcome and amount. A pending line is never
     * matched this way: it is written by the core before a call, and two calls are two.
     */
    private function replayedReferenceLessLine(int $orderId, PaymentTransactionType $type, PaymentTransactionState $state, string $amount): ?OrderPaymentTransaction
    {
        if (!$state->isSettled()) {
            return null;
        }

        return OrderPaymentTransactionQuery::create()->findRecentWithoutReference(
            $orderId,
            $type,
            $state,
            $amount,
            new \DateTimeImmutable('-'.self::REPLAY_WINDOW_SECONDS.' seconds'),
        );
    }

    /**
     * The pending line a notification is about when the journal does not know its
     * reference: a call that timed out, or whose answer could not be recorded, left the
     * line without the reference the provider gave the movement. It is adopted when it is
     * the only pending line of that movement and amount without a reference; two of them
     * cannot be told apart, and neither is guessed at. A void has no amount of its own to
     * match on — it releases whatever is left — and is matched on the movement alone.
     */
    private function lineWaitingForItsReference(int $orderId, PaymentTransactionType $type, ?string $amount = null): ?OrderPaymentTransaction
    {
        $waiting = OrderPaymentTransactionQuery::create()->findPendingWithoutReference($orderId, $type, $amount);

        return 1 === \count($waiting) ? $waiting->getFirst() : null;
    }

    private function assertCaptureFitsAuthorization(Order $order, float|string $amount): null
    {
        $totals = $this->totalsReader->forOrder((int) $order->getId());

        // Without an authorization the capture is the payment itself: the module took
        // the price at once, there is no reservation to stay within.
        if (!$totals->hasAuthorization()) {
            return null;
        }

        $normalizedAmount = PaymentAmount::normalize($amount);

        if (!$totals->allows($normalizedAmount)) {
            throw new CaptureExceedsAuthorizationException((string) $order->getRef(), $normalizedAmount, $totals->remainingToCapture);
        }

        return null;
    }

    private function announce(Order $order, OrderPaymentTransaction $transaction, ?string $moduleCode): void
    {
        $this->eventDispatcher->dispatch(
            new OrderPaymentTransactionEvent($order, $transaction, $moduleCode),
            TheliaEvents::ORDER_PAYMENT_TRANSACTION_RECORDED,
        );
    }

    /**
     * The journal knows three authors: the provider through its module, an
     * administrator, or the shop itself. A customer in session is the shop acting on
     * the provider's return, never the author of a money movement.
     */
    private function actorOf(?string $moduleCode): OrderHistoryActor
    {
        $actor = $this->actorResolver->resolve($moduleCode);

        return OrderHistoryActorType::CUSTOMER === $actor->actorType ? OrderHistoryActor::system() : $actor;
    }

    /**
     * Under the lock a duplicate key can only mean a reference another line of the same
     * movement already carries: settling or naming a line with it is refused.
     */
    private function save(OrderPaymentTransaction $transaction): void
    {
        try {
            $transaction->save();
        } catch (PropelException|\RuntimeException $exception) {
            // Propel raises an insert or an update the server refused as a
            // QueryExecutionException, which is not a PropelException.
            if (!$this->isDuplicateKey($exception)) {
                throw $exception;
            }

            // Propel keeps an object whose save failed flagged as being saved, and silently
            // ignores any later save of it: it leaves the pool, and whoever has to write the
            // line again reads it afresh.
            OrderPaymentTransactionTableMap::removeInstanceFromPool($transaction);

            throw new ConflictingPaymentReferenceException(\sprintf('The reference %s is already carried by another %s of this order.', (string) $transaction->getPspReference(), (string) $transaction->getType()), 0, $exception);
        }
    }

    /**
     * The line as the database holds it, in a new object: one whose save failed cannot be
     * saved again.
     */
    private function freshCopyOf(OrderPaymentTransaction $transaction): OrderPaymentTransaction
    {
        OrderPaymentTransactionTableMap::removeInstanceFromPool($transaction);

        return OrderPaymentTransactionQuery::create()->findPk($transaction->getId())
            ?? throw new PaymentException(\sprintf('Payment transaction #%d no longer exists.', (int) $transaction->getId()));
    }

    private function cleanReference(?string $pspReference): ?string
    {
        if (null === $pspReference) {
            return null;
        }

        $pspReference = trim($pspReference);

        return '' === $pspReference ? null : $pspReference;
    }

    private function isDuplicateKey(\Throwable $exception): bool
    {
        $previous = $exception;

        while (null !== $previous) {
            if ($previous instanceof \PDOException && self::DUPLICATE_KEY_SQLSTATE === (string) $previous->getCode()) {
                return true;
            }

            $previous = $previous->getPrevious();
        }

        return false;
    }
}
