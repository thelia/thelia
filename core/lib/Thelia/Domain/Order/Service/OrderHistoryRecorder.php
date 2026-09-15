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

namespace Thelia\Domain\Order\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Log\Tlog;
use Thelia\Model\Map\OrderHistoryTableMap;
use Thelia\Model\OrderHistory;
use Thelia\Model\OrderHistoryQuery;

/**
 * Writes the timestamped history of an order.
 *
 * Two rules hold for every entry written here.
 *
 * The journal never breaks the gesture it records: a history that cannot be written is
 * logged and forgotten, so a payment notification, a status change or an address
 * correction never fails because of its own audit trail.
 *
 * An automatic entry is written once. Payment providers notify twice, a browser reloads
 * a confirmation page, a cron retries: the same event with the same payload arriving
 * again adds nothing and is dropped, whether the two arrivals follow each other or land
 * on two workers at the same instant. A note is the exception — a human writing the same
 * sentence twice meant to write it twice.
 */
final readonly class OrderHistoryRecorder
{
    private const LOCK_NAME_PREFIX = 'thelia_order_history:';

    /**
     * MySQL refuses a user lock name longer than 64 bytes; MariaDB accepts 192. The
     * shorter of the two is the one a name has to fit.
     */
    private const LOCK_NAME_MAX_LENGTH = 64;

    /**
     * How long a worker waits, in seconds, for the worker that is already writing the
     * same kind of event on the same order. Short on purpose: past that, the entry is
     * written unchecked rather than kept waiting.
     */
    private const LOCK_TIMEOUT = 1;

    public function __construct(
        private OrderHistoryActorResolver $actorResolver,
    ) {
    }

    /**
     * The name of the lock that serializes the deduplication of one kind of event on
     * one order. Public because it is what an operator sees in the server's lock
     * tables, and what a test has to take to stage a concurrent worker.
     */
    public static function lockNameFor(int $orderId, string $eventType): string
    {
        $lockName = self::LOCK_NAME_PREFIX.$orderId.':'.$eventType;

        if (\strlen($lockName) <= self::LOCK_NAME_MAX_LENGTH) {
            return $lockName;
        }

        // event_type is a free VARCHAR(50) that a module fills as it likes, and a long
        // one pushes the name past what the server accepts. A digest of the event type
        // keeps the name inside the limit, identical from one process to the next, and
        // distinct from one event type to another.
        return self::LOCK_NAME_PREFIX.$orderId.':'.substr(sha1($eventType), 0, 16);
    }

    /**
     * @param array<string, mixed> $payload codes and references only, never personal data
     */
    public function record(
        int $orderId,
        string $eventType,
        array $payload = [],
        ?string $comment = null,
        bool $visibleToCustomer = false,
        ?string $moduleCode = null,
    ): void {
        try {
            $encodedPayload = $this->encodePayload($payload);
            $connection = Propel::getConnection(OrderHistoryTableMap::DATABASE_NAME);

            if (!$this->isDeduplicated($eventType)) {
                $this->write($orderId, $eventType, $encodedPayload, $comment, $visibleToCustomer, $moduleCode, $connection);

                return;
            }

            $lockName = self::lockNameFor($orderId, $eventType);
            $lockHeld = $this->acquireLock($connection, $lockName);

            try {
                // Without the lock the check is worthless — the worker holding it is
                // about to write the very row this one would look for — so it is
                // skipped rather than trusted, and the entry is written unchecked.
                if ($lockHeld && $this->isDuplicate($orderId, $eventType, $encodedPayload, $connection)) {
                    return;
                }

                $this->write($orderId, $eventType, $encodedPayload, $comment, $visibleToCustomer, $moduleCode, $connection);
            } finally {
                if ($lockHeld) {
                    $this->releaseLock($connection, $lockName);
                }
            }
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->err(
                'Failed to record order history entry {type} on order {order}: {ex}',
                ['type' => $eventType, 'order' => $orderId, 'ex' => $throwable],
            );
        }
    }

    public function recordOrderCreated(int $orderId, ?string $orderRef, ?string $moduleCode = null): void
    {
        $this->record(
            $orderId,
            OrderHistoryEventType::ORDER_CREATED->value,
            array_filter(['order_ref' => $orderRef], static fn ($value): bool => null !== $value),
            moduleCode: $moduleCode,
        );
    }

    public function recordStatusChanged(
        int $orderId,
        ?string $fromStatusCode,
        string $toStatusCode,
        ?string $moduleCode = null,
    ): void {
        // A transition to the status the order already has is not a change: the back
        // office resubmits the same status, and a gateway notifies "paid" on an order
        // that is already paid.
        if ($fromStatusCode === $toStatusCode) {
            return;
        }

        $this->record(
            $orderId,
            OrderHistoryEventType::STATUS_CHANGED->value,
            ['from' => $fromStatusCode, 'to' => $toStatusCode],
            moduleCode: $moduleCode,
        );
    }

    public function recordDeliveryRefUpdated(int $orderId, ?string $deliveryRef, ?string $moduleCode = null): void
    {
        $this->record(
            $orderId,
            OrderHistoryEventType::DELIVERY_REF_UPDATED->value,
            ['delivery_ref' => $deliveryRef],
            moduleCode: $moduleCode,
        );
    }

    public function recordTransactionRefUpdated(int $orderId, ?string $transactionRef, ?string $moduleCode = null): void
    {
        $this->record(
            $orderId,
            OrderHistoryEventType::TRANSACTION_REF_UPDATED->value,
            ['transaction_ref' => $transactionRef],
            moduleCode: $moduleCode,
        );
    }

    public function recordAddressUpdated(
        int $orderId,
        int $orderAddressId,
        ?string $addressType = null,
        ?string $moduleCode = null,
    ): void {
        $this->record(
            $orderId,
            OrderHistoryEventType::ADDRESS_UPDATED->value,
            ['order_address_id' => $orderAddressId, 'address_type' => $addressType],
            moduleCode: $moduleCode,
        );
    }

    public function recordInvoiceRefAllocated(int $orderId, string $invoiceRef, ?string $moduleCode = null): void
    {
        $this->record(
            $orderId,
            OrderHistoryEventType::INVOICE_REF_ALLOCATED->value,
            ['invoice_ref' => $invoiceRef],
            moduleCode: $moduleCode,
        );
    }

    public function recordEmailSent(int $orderId, string $messageCode, ?string $moduleCode = null): void
    {
        $this->record(
            $orderId,
            OrderHistoryEventType::EMAIL_SENT->value,
            ['message_code' => $messageCode],
            moduleCode: $moduleCode,
        );
    }

    public function recordNote(
        int $orderId,
        string $comment,
        bool $visibleToCustomer = false,
        ?string $moduleCode = null,
    ): void {
        $this->record(
            $orderId,
            OrderHistoryEventType::NOTE->value,
            comment: $comment,
            visibleToCustomer: $visibleToCustomer,
            moduleCode: $moduleCode,
        );
    }

    private function write(
        int $orderId,
        string $eventType,
        ?string $encodedPayload,
        ?string $comment,
        bool $visibleToCustomer,
        ?string $moduleCode,
        ConnectionInterface $connection,
    ): void {
        $actor = $this->actorResolver->resolve($moduleCode);

        $entry = new OrderHistory();
        $entry
            ->setOrderId($orderId)
            ->setEventType($eventType)
            ->setActorType($actor->actorType->value)
            ->setActorLabel($actor->label)
            ->setAdminId($actor->adminId)
            ->setPayload($encodedPayload)
            ->setComment($comment)
            ->setVisibleToCustomer($visibleToCustomer ? 1 : 0)
            ->save($connection);
    }

    /**
     * Whether entries of this kind are checked against the journal before being written.
     *
     * A note is the only kind that is not: a human writing the same sentence twice meant
     * to write it twice, so a note is never compared and never waits on the lock.
     */
    private function isDeduplicated(string $eventType): bool
    {
        return OrderHistoryEventType::NOTE->value !== $eventType;
    }

    /**
     * Takes the lock that serializes the check and the write for one couple
     * (order, event type), waiting at most {@see self::LOCK_TIMEOUT} seconds for it.
     *
     * Returns false rather than throwing when the lock cannot be had — the caller then
     * writes without checking. A lock is what makes the journal tidy, never what makes
     * it work.
     */
    private function acquireLock(ConnectionInterface $connection, string $lockName): bool
    {
        try {
            $statement = $connection->prepare('SELECT GET_LOCK(?, ?)');
            $statement->bindValue(1, $lockName, \PDO::PARAM_STR);
            $statement->bindValue(2, self::LOCK_TIMEOUT, \PDO::PARAM_INT);
            $statement->execute();

            // 1 when granted, 0 when the wait ran out, NULL on a server-side error.
            return '1' === (string) $statement->fetchColumn();
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->warning(
                'Order history deduplication lock {lock} could not be requested, writing unchecked: {ex}',
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
            // The server drops the lock when the connection goes, which is the only way
            // this statement fails: nothing is leaked, but the failure is worth knowing.
            Tlog::getInstance()->warning(
                'Order history deduplication lock {lock} could not be released: {ex}',
                ['lock' => $lockName, 'ex' => $throwable],
            );
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws \JsonException
     */
    private function encodePayload(array $payload): ?string
    {
        if ([] === $payload) {
            return null;
        }

        return json_encode($payload, \JSON_THROW_ON_ERROR);
    }

    /**
     * Whether the same automatic event, with the same payload, is already the last one
     * of its kind on this order.
     *
     * Only the latest entry is compared: an event that happened, then something else,
     * then happened again is a real second occurrence and belongs in the timeline.
     *
     * The answer is only worth what the moment it was read is worth, so the caller runs
     * this check and the write that follows inside a named database lock held on the
     * connection that writes — one lock per couple (order, event type), so two workers
     * handling the same notification at the same instant take their turn instead of both
     * reading an empty journal. That is why this method is never called on its own.
     *
     * The lock is asked for, never insisted on: a worker waits a second at most, and an
     * entry whose turn never came is written without being checked. A duplicated line is
     * a blemish in a timeline; a payment notification failing on its own audit trail is
     * an incident.
     */
    private function isDuplicate(int $orderId, string $eventType, ?string $encodedPayload, ConnectionInterface $connection): bool
    {
        $latestEntry = OrderHistoryQuery::create()->findLatestOfType($orderId, $eventType, $connection);

        return null !== $latestEntry && $latestEntry->getPayload() === $encodedPayload;
    }
}
