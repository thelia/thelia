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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderPaymentTransactionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\DTO\OrderHistoryActor;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Service\OrderHistoryActorResolver;
use Thelia\Domain\Payment\Enum\PaymentTransactionState;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Domain\Payment\Exception\CaptureExceedsAuthorizationException;
use Thelia\Domain\Payment\Exception\InvalidPaymentAmountException;
use Thelia\Domain\Payment\Exception\PaymentException;
use Thelia\Log\Tlog;
use Thelia\Model\Map\OrderPaymentTransactionTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentTransaction;
use Thelia\Model\OrderPaymentTransactionQuery;

/**
 * Writes the payment journal of an order.
 *
 * One method per movement rather than a generic save: each one knows what it has to
 * check. A capture never exceeds what the authorization still holds, a refund never
 * exceeds what was taken, and a provider reference already in the journal for the
 * same movement writes nothing — the line that carries it is answered instead.
 *
 * A failure here is not swallowed, unlike an order history entry: a payment whose
 * trace cannot be written is a payment the merchant cannot account for.
 */
final readonly class PaymentTransactionRecorder
{
    private const LOCK_NAME_PREFIX = 'thelia_order_payment:';

    /**
     * How long a worker waits, in seconds, for the worker already writing the journal
     * of the same order.
     */
    private const LOCK_TIMEOUT = 2;

    private const DUPLICATE_KEY_SQLSTATE = '23000';

    public function __construct(
        private OrderHistoryActorResolver $actorResolver,
        private PaymentTransactionTotalsReader $totalsReader,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

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
            throw new InvalidPaymentAmountException(\sprintf('Order %s: an authorization needs a positive amount, %s given.', (string) $order->getRef(), PaymentAmount::normalize($amount)));
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
            throw new InvalidPaymentAmountException(\sprintf('Order %s: a capture cannot be negative, %s given.', (string) $order->getRef(), PaymentAmount::normalize($amount)));
        }

        if (PaymentTransactionState::FAILED !== $state) {
            $this->assertCaptureFitsAuthorization($order, $amount);
        }

        return $this->record($order, PaymentTransactionType::CAPTURE, $amount, $state, $pspReference, $authorization, $moduleCode, $errorCode, $errorMessage);
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
            throw new InvalidPaymentAmountException(\sprintf('Order %s: a refund needs a positive amount, %s given.', (string) $order->getRef(), PaymentAmount::normalize($amount)));
        }

        if (PaymentTransactionState::FAILED !== $state) {
            $netCaptured = $this->totalsReader->forOrder((int) $order->getId())->netCaptured();

            if (PaymentAmount::compare($amount, $netCaptured) > 0) {
                throw new InvalidPaymentAmountException(\sprintf('Order %s: a refund of %s exceeds the %s taken and not yet given back.', (string) $order->getRef(), PaymentAmount::normalize($amount), $netCaptured));
            }
        }

        return $this->record($order, PaymentTransactionType::REFUND, $amount, $state, $pspReference, $capture, $moduleCode, $errorCode, $errorMessage);
    }

    /**
     * Releases what the authorization still holds. The amount is what is left to
     * capture at that moment, so the totals read zero afterwards.
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
        $remaining = $this->totalsReader->forOrder((int) $order->getId())->remainingToCapture;

        if (PaymentTransactionState::FAILED !== $state && !PaymentAmount::isPositive($remaining)) {
            throw new InvalidPaymentAmountException(\sprintf('Order %s: no authorization holds anything to release.', (string) $order->getRef()));
        }

        return $this->record($order, PaymentTransactionType::VOID, $remaining, $state, $pspReference, $authorization, $moduleCode, $errorCode, $errorMessage);
    }

    /**
     * The capture line of an order paid by a module that does not keep a journal:
     * written once, when the order first reaches a paid status, and never again.
     *
     * The check and the write run under a lock on the order, so two notifications of
     * the same payment handled at the same instant leave one line. A lock that cannot
     * be had is not waited on: the line is then written after an unguarded check.
     */
    public function recordImmediateCaptureIfNone(
        Order $order,
        float|string $amount,
        ?string $pspReference = null,
        ?string $moduleCode = null,
    ): ?OrderPaymentTransaction {
        $orderId = (int) $order->getId();
        $connection = Propel::getConnection(OrderPaymentTransactionTableMap::DATABASE_NAME);
        $lockName = self::LOCK_NAME_PREFIX.$orderId;
        $lockHeld = $this->acquireLock($connection, $lockName);

        try {
            if (OrderPaymentTransactionQuery::create()->hasSucceededCapture($orderId, $connection)) {
                return null;
            }

            return $this->recordCapture($order, $amount, $pspReference, moduleCode: $moduleCode);
        } finally {
            if ($lockHeld) {
                $this->releaseLock($connection, $lockName);
            }
        }
    }

    /**
     * Gives a pending line its outcome. The only change a line ever receives.
     */
    public function settle(
        OrderPaymentTransaction $transaction,
        PaymentTransactionState $state,
        ?string $pspReference = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?string $moduleCode = null,
    ): OrderPaymentTransaction {
        if (!$transaction->isPending()) {
            throw new PaymentException(\sprintf('Payment transaction #%d is already %s and cannot be settled again.', (int) $transaction->getId(), (string) $transaction->getState()));
        }

        if (!$state->isSettled()) {
            throw new \InvalidArgumentException('A pending line is settled to succeeded or failed, not left pending.');
        }

        $pspReference = $this->cleanReference($pspReference);

        $transaction->setState($state->value);

        if (null !== $pspReference) {
            $transaction->setPspReference($pspReference);
        }

        $transaction
            ->setErrorCode($errorCode)
            ->setErrorMessage($errorMessage);

        $this->save($transaction, Propel::getConnection(OrderPaymentTransactionTableMap::DATABASE_NAME));

        $this->eventDispatcher->dispatch(
            new OrderPaymentTransactionEvent($transaction->getOrder(), $transaction, $moduleCode),
            TheliaEvents::ORDER_PAYMENT_TRANSACTION_RECORDED,
        );

        return $transaction;
    }

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
    ): OrderPaymentTransaction {
        $orderId = (int) $order->getId();
        $pspReference = $this->cleanReference($pspReference);
        $connection = Propel::getConnection(OrderPaymentTransactionTableMap::DATABASE_NAME);

        if (null !== $pspReference) {
            $existing = OrderPaymentTransactionQuery::create()->findByReference($orderId, $type, $pspReference, $connection);

            if (null !== $existing) {
                return $existing;
            }
        }

        if (null !== $parent && (int) $parent->getOrderId() !== $orderId) {
            throw new \InvalidArgumentException(\sprintf('Payment transaction #%d belongs to another order than %s.', (int) $parent->getId(), (string) $order->getRef()));
        }

        $actor = $this->actorOf($moduleCode);

        $transaction = new OrderPaymentTransaction();
        $transaction
            ->setOrderId($orderId)
            ->setType($type->value)
            ->setState($state->value)
            ->setAmount(PaymentAmount::normalize($amount))
            ->setCurrencyId((int) $order->getCurrencyId())
            ->setPspReference($pspReference)
            ->setParentId($parent?->getId())
            ->setPaymentModuleId($order->getPaymentModuleId())
            ->setActorType($actor->actorType->value)
            ->setActorLabel($actor->label)
            ->setAdminId($actor->adminId)
            ->setErrorCode($errorCode)
            ->setErrorMessage($errorMessage);

        try {
            $this->save($transaction, $connection);
        } catch (PropelException $exception) {
            // The unique index caught a notification replayed between the check above
            // and this write: the line it wrote is the one to answer.
            if (null !== $pspReference && $this->isDuplicateKey($exception)) {
                $existing = OrderPaymentTransactionQuery::create()->findByReference($orderId, $type, $pspReference, $connection);

                if (null !== $existing) {
                    return $existing;
                }
            }

            throw $exception;
        }

        $this->eventDispatcher->dispatch(
            new OrderPaymentTransactionEvent($order, $transaction, $moduleCode),
            TheliaEvents::ORDER_PAYMENT_TRANSACTION_RECORDED,
        );

        return $transaction;
    }

    private function assertCaptureFitsAuthorization(Order $order, float|string $amount): void
    {
        $totals = $this->totalsReader->forOrder((int) $order->getId());

        // Without an authorization the capture is the payment itself: the module took
        // the price at once, there is no reservation to stay within.
        if (!$totals->hasAuthorization()) {
            return;
        }

        $normalizedAmount = PaymentAmount::normalize($amount);

        if (!$totals->allows($normalizedAmount)) {
            throw new CaptureExceedsAuthorizationException((string) $order->getRef(), $normalizedAmount, $totals->remainingToCapture);
        }
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

    private function save(OrderPaymentTransaction $transaction, ConnectionInterface $connection): void
    {
        $transaction->save($connection);
    }

    private function cleanReference(?string $pspReference): ?string
    {
        if (null === $pspReference) {
            return null;
        }

        $pspReference = trim($pspReference);

        return '' === $pspReference ? null : $pspReference;
    }

    private function isDuplicateKey(PropelException $exception): bool
    {
        $previous = $exception->getPrevious();

        while (null !== $previous) {
            if ($previous instanceof \PDOException && self::DUPLICATE_KEY_SQLSTATE === (string) $previous->getCode()) {
                return true;
            }

            $previous = $previous->getPrevious();
        }

        return false;
    }

    private function acquireLock(ConnectionInterface $connection, string $lockName): bool
    {
        try {
            $statement = $connection->prepare('SELECT GET_LOCK(?, ?)');
            $statement->bindValue(1, $lockName, \PDO::PARAM_STR);
            $statement->bindValue(2, self::LOCK_TIMEOUT, \PDO::PARAM_INT);
            $statement->execute();

            return '1' === (string) $statement->fetchColumn();
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->warning(
                'Payment journal lock {lock} could not be requested, writing unchecked: {ex}',
                ['lock' => $lockName, 'ex' => $throwable],
            );

            return false;
        }
    }

    private function releaseLock(ConnectionInterface $connection, string $lockName): void
    {
        try {
            $statement = $connection->prepare('SELECT RELEASE_LOCK(?)');
            $statement->bindValue(1, $lockName, \PDO::PARAM_STR);
            $statement->execute();
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->warning(
                'Payment journal lock {lock} could not be released: {ex}',
                ['lock' => $lockName, 'ex' => $throwable],
            );
        }
    }
}
