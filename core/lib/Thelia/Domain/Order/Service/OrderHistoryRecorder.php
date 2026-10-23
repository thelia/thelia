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
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Log\Tlog;
use Thelia\Model\Map\OrderHistoryTableMap;
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
 * again right behind itself adds nothing and is dropped, whether the two arrivals follow
 * each other or land on two workers at the same instant. A note and an address
 * correction are the exceptions: each is a gesture of a person, never a replay.
 *
 * That second rule holds in autocommit only. A caller that wraps its gesture in a
 * transaction of its own (the API order processor, a payment module) releases nothing
 * to the other workers before it commits: the lock below is released when the entry is
 * written, which is before that commit, so a worker that takes it next cannot see the
 * row the first one has not committed yet; and under REPEATABLE READ the view of the
 * worker is frozen at its first read, which the caller made before the lock was asked
 * for. Two workers in two such transactions can both write the same entry. A duplicated
 * line is accepted there, as it is when the lock cannot be had at all: the mechanism is
 * what it is, and the first rule, a journal that never breaks its gesture, comes first.
 */
final readonly class OrderHistoryRecorder
{
    private const LOCK_NAME_PREFIX = 'thelia_order_history:';

    /**
     * The kinds whose payload is the new value of something the order holds: a status, a
     * reference, the fact of being placed. A repeat of the same value is a replay even
     * with other lines written since, because the value itself would have moved on in
     * between — a status changes from the one it has — and the lines a gesture writes
     * after the one that announces it (the invoice number allocated when an order turns
     * paid, the e-mail it sends) are not a reason to take the replay of that
     * announcement for a new occurrence. Those kinds are compared with the last entry of
     * their own kind; every other kind, an e-mail for one, is compared with the last
     * entry of the order.
     */
    private const VALUE_EVENT_TYPES = [
        OrderHistoryEventType::ORDER_CREATED,
        OrderHistoryEventType::STATUS_CHANGED,
        OrderHistoryEventType::DELIVERY_REF_UPDATED,
        OrderHistoryEventType::TRANSACTION_REF_UPDATED,
        OrderHistoryEventType::INVOICE_REF_ALLOCATED,
    ];

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

    /**
     * The width of the columns an entry fills from outside: the type is a VARCHAR(50)
     * that a module chooses, the label a VARCHAR(255) that receives a login, a customer
     * reference or a module code. A longer value is refused by the database in strict
     * mode and cut by it otherwise; bounded here, the line is the same on both.
     */
    private const EVENT_TYPE_MAX_LENGTH = 50;

    private const ACTOR_LABEL_MAX_LENGTH = 255;

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
        $connection = null;
        $callerCanCommit = false;

        try {
            if (mb_strlen($eventType) > self::EVENT_TYPE_MAX_LENGTH) {
                throw new \InvalidArgumentException(\sprintf('An order history event type holds %d characters at most.', self::EVENT_TYPE_MAX_LENGTH));
            }

            $encodedPayload = $this->encodePayload($payload);
            $connection = Propel::getConnection(OrderHistoryTableMap::DATABASE_NAME);
            $callerCanCommit = $this->canCommit($connection);

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

            // The line is written by a statement of its own, which leaves the transaction
            // of the caller alone. The check stays for what a refusal can still do: a
            // module that rolls back the connection from a listener it was called from,
            // or a server that aborted the transaction (a deadlock). The caller's commit
            // then throws and the gesture is lost with its audit trail; it cannot be
            // undone from here, so it is at least said.
            if ($callerCanCommit && null !== $connection && !$this->canCommit($connection)) {
                Tlog::getInstance()->err(
                    'The failed order history entry {type} on order {order} left the transaction of the caller not committable.',
                    ['type' => $eventType, 'order' => $orderId],
                );
            }
        }
    }

    /**
     * Whether a transaction is open and still able to commit; false in autocommit, where
     * there is nothing for a failed write to take away.
     */
    private function canCommit(ConnectionInterface $connection): bool
    {
        return $connection instanceof ConnectionWrapper && $connection->isCommitable();
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

    /**
     * Inserts the line with a prepared statement of its own, never through
     * {@see \Thelia\Model\OrderHistory::save()}.
     *
     * save() opens a transaction level and rolls it back on any refusal (a foreign key
     * on an order that is gone, a lock wait that ran out, a payload too big for the
     * server). Propel then marks the transaction of the caller as not committable, and
     * the caller's commit throws: the status change the line was recording is lost with
     * it (the API order processor wraps the change in a transaction). A statement that
     * fails on its own undoes the statement only, so the caller's transaction is left as
     * it was and the refusal is logged by {@see self::record()}.
     *
     * The columns and the values are the ones save() writes, the two timestamps of the
     * timestampable behavior included.
     *
     * One refusal cannot be absorbed this way: a deadlock (error 1213) makes InnoDB roll
     * back the whole transaction on the server, whatever the client does next, and the
     * caller's gesture goes with it. Nothing written here can prevent that.
     */
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
        $actorLabel = null === $actor->label ? null : mb_substr($actor->label, 0, self::ACTOR_LABEL_MAX_LENGTH);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');

        $statement = $connection->prepare(
            'INSERT INTO `order_history` '
            .'(`order_id`, `event_type`, `actor_type`, `actor_label`, `admin_id`, `payload`, `comment`, `visible_to_customer`, `created_at`, `updated_at`) '
            .'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $statement->bindValue(1, $orderId, \PDO::PARAM_INT);
        $statement->bindValue(2, $eventType, \PDO::PARAM_STR);
        $statement->bindValue(3, $actor->actorType->value, \PDO::PARAM_STR);
        $statement->bindValue(4, $actorLabel, null === $actorLabel ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $statement->bindValue(5, $actor->adminId, null === $actor->adminId ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $statement->bindValue(6, $encodedPayload, null === $encodedPayload ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $statement->bindValue(7, $comment, null === $comment ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $statement->bindValue(8, $visibleToCustomer ? 1 : 0, \PDO::PARAM_INT);
        $statement->bindValue(9, $now, \PDO::PARAM_STR);
        $statement->bindValue(10, $now, \PDO::PARAM_STR);
        $statement->execute();
    }

    /**
     * Whether entries of this kind are checked against the journal before being written.
     *
     * A note and an address correction are not. A human writing the same sentence twice
     * meant to write it twice. An administrator correcting an address is a gesture, not
     * a notification that can be delivered twice, and its payload (the address and its
     * type, never what was typed) is the same from one correction to the next, so a
     * comparison would take the second correction, and its author, for a replay.
     * Neither waits on the lock.
     */
    private function isDeduplicated(string $eventType): bool
    {
        return !\in_array($eventType, [
            OrderHistoryEventType::NOTE->value,
            OrderHistoryEventType::ADDRESS_UPDATED->value,
        ], true);
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

    private function isValueEvent(string $eventType): bool
    {
        $known = OrderHistoryEventType::tryFrom($eventType);

        return null !== $known && \in_array($known, self::VALUE_EVENT_TYPES, true);
    }

    /**
     * Whether the same automatic event, with the same payload, is already the last entry
     * it repeats.
     *
     * For an occurrence (an e-mail, a module's own step) that is the latest entry of the
     * order, whatever its kind: an event that happened, then something else, then
     * happened again is a real second occurrence and belongs in the timeline. Comparing
     * with the latest entry of the same kind would drop it, and the same e-mail sent on
     * each step of a return would leave one line. A replay — a notification delivered
     * twice, a cron that retries — arrives right behind the entry it repeats and is
     * still dropped. For the kinds listed in {@see self::VALUE_EVENT_TYPES} it is the
     * latest entry of the same kind, for the reason given there.
     *
     * The answer is only worth what the moment it was read is worth, so the caller runs
     * this check and the write that follows inside a named database lock held on the
     * connection that writes — one lock per couple (order, event type), so two workers
     * handling the same notification at the same instant take their turn instead of both
     * reading an empty journal. That is why this method is never called on its own.
     *
     * The lock stays per kind although the comparison of an e-mail now reads the whole
     * order. It serializes two identical e-mails, which is the race that writes a line
     * twice. It does not serialize an e-mail against another kind of entry written at the
     * same instant: whichever lands first decides whether the e-mail looks like a replay,
     * and a line more or less in that window is accepted, where a lock per order would
     * make every writer of an order queue behind the others.
     *
     * The lock is asked for, never insisted on: a worker waits a second at most, and an
     * entry whose turn never came is written without being checked. A duplicated line is
     * a blemish in a timeline; a payment notification failing on its own audit trail is
     * an incident.
     */
    private function isDuplicate(int $orderId, string $eventType, ?string $encodedPayload, ConnectionInterface $connection): bool
    {
        $latestEntry = $this->isValueEvent($eventType)
            ? OrderHistoryQuery::create()->findLatestOfType($orderId, $eventType, $connection)
            : OrderHistoryQuery::create()->findLatest($orderId, $connection);

        return null !== $latestEntry
            && $latestEntry->getEventType() === $eventType
            && $latestEntry->getPayload() === $encodedPayload;
    }
}
