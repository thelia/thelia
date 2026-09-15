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

namespace Thelia\Tests\Integration\Domain\Order;

use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Service\OrderHistoryRecorder;
use Thelia\Model\Map\OrderHistoryTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The journal deduplicates an automatic event by reading the last entry of its kind
 * before writing a new one. Two workers handling the same notification at the same
 * instant would both read "nothing identical yet" and both write, so the pair
 * read-then-write is serialized by a named database lock.
 *
 * Real parallelism is out of reach of a single PHPUnit process, so the interleaving
 * is staged instead: a second, genuinely distinct database connection takes the lock
 * the recorder is about to ask for, and the recorder is driven while that lock is
 * held. What the lock does to the recorder is then observable from one process.
 */
final class OrderHistoryConcurrencyTest extends IntegrationTestCase
{
    private ?ConnectionInterface $rivalConnection = null;

    /**
     * @var list<string>
     */
    private array $locksHeldByTheRival = [];

    protected function tearDown(): void
    {
        foreach ($this->locksHeldByTheRival as $lockName) {
            $this->askTheRivalFor('SELECT RELEASE_LOCK(?)', $lockName);
        }

        $this->locksHeldByTheRival = [];
        $this->rivalConnection = null;

        parent::tearDown();
    }

    public function testWithTheLockFreeTheSameEventTwiceStillLeavesASingleLine(): void
    {
        $order = $this->createFixtureFactory()->order();
        $recorder = $this->getService(OrderHistoryRecorder::class);

        $recorder->recordTransactionRefUpdated($order->getId(), 'TXN-42');
        $recorder->recordTransactionRefUpdated($order->getId(), 'TXN-42');

        self::assertCount(1, $this->transactionRefEntriesOf($order));
    }

    public function testAnEventWhoseLockIsHeldElsewhereIsWrittenRatherThanDelayed(): void
    {
        $order = $this->createFixtureFactory()->order();
        $recorder = $this->getService(OrderHistoryRecorder::class);

        $recorder->recordTransactionRefUpdated($order->getId(), 'TXN-42');
        self::assertCount(1, $this->transactionRefEntriesOf($order));

        $this->haveTheRivalHold(
            OrderHistoryRecorder::lockNameFor(
                $order->getId(),
                OrderHistoryEventType::TRANSACTION_REF_UPDATED->value,
            ),
        );

        $startedAt = microtime(true);
        $recorder->recordTransactionRefUpdated($order->getId(), 'TXN-42');
        $elapsed = microtime(true) - $startedAt;

        // The entry the recorder could not check is written anyway: a duplicated line
        // is a cosmetic flaw, a payment notification that fails on its own audit trail
        // is not.
        self::assertCount(
            2,
            $this->transactionRefEntriesOf($order),
            'an entry that could not be checked against the journal must still be written',
        );

        self::assertGreaterThan(
            0.5,
            $elapsed,
            'the recorder is expected to wait for the lock before giving up on deduplication',
        );
        self::assertLessThan(
            3.0,
            $elapsed,
            'waiting for the lock must stay bounded: no order is held back by its journal',
        );
    }

    public function testTheLockIsReleasedOnceTheEntryIsWritten(): void
    {
        $order = $this->createFixtureFactory()->order();
        $lockName = OrderHistoryRecorder::lockNameFor(
            $order->getId(),
            OrderHistoryEventType::TRANSACTION_REF_UPDATED->value,
        );

        $this->getService(OrderHistoryRecorder::class)
            ->recordTransactionRefUpdated($order->getId(), 'TXN-42');

        self::assertSame(
            1,
            $this->haveTheRivalTry($lockName),
            'the lock the recorder took must be free again as soon as it is done',
        );
    }

    public function testANoteTakesNoLockAtAll(): void
    {
        $order = $this->createFixtureFactory()->order();
        $recorder = $this->getService(OrderHistoryRecorder::class);

        $this->haveTheRivalHold(
            OrderHistoryRecorder::lockNameFor($order->getId(), OrderHistoryEventType::NOTE->value),
        );

        $startedAt = microtime(true);
        $recorder->recordNote($order->getId(), 'Called the customer back.');
        $recorder->recordNote($order->getId(), 'Called the customer back.');
        $elapsed = microtime(true) - $startedAt;

        $notes = OrderHistoryQuery::create()
            ->filterByOrderId($order->getId())
            ->filterByEventType(OrderHistoryEventType::NOTE->value)
            ->find();

        self::assertCount(2, $notes, 'two identical notes are two notes');
        self::assertLessThan(
            0.5,
            $elapsed,
            'a note is never deduplicated, so it never waits on the deduplication lock',
        );
    }

    public function testTheLockNameNamesTheOrderAndTheEventAndFitsTheServerLimit(): void
    {
        self::assertSame(
            'thelia_order_history:42:transaction_ref_updated',
            OrderHistoryRecorder::lockNameFor(42, 'transaction_ref_updated'),
        );

        // event_type is a VARCHAR(50) a module fills as it likes, and MySQL refuses a
        // lock name over 64 bytes — the longest event type on the highest order id must
        // still produce a usable name.
        $longest = OrderHistoryRecorder::lockNameFor(2147483647, str_repeat('e', 50));

        self::assertLessThanOrEqual(64, \strlen($longest));
        self::assertNotSame(
            $longest,
            OrderHistoryRecorder::lockNameFor(2147483647, str_repeat('f', 50)),
            'two event types that differ must not share a lock name',
        );
        self::assertSame(
            $longest,
            OrderHistoryRecorder::lockNameFor(2147483647, str_repeat('e', 50)),
            'the same couple must always produce the same lock name',
        );
        self::assertSame(1, $this->haveTheRivalTry($longest), 'the shortened name is a valid lock name');
    }

    /**
     * @return list<\Thelia\Model\OrderHistory>
     */
    private function transactionRefEntriesOf(Order $order): array
    {
        return array_values(
            OrderHistoryQuery::create()
                ->filterByOrderId($order->getId())
                ->filterByEventType(OrderHistoryEventType::TRANSACTION_REF_UPDATED->value)
                ->find()
                ->getData(),
        );
    }

    private function haveTheRivalHold(string $lockName): void
    {
        self::assertSame(1, $this->haveTheRivalTry($lockName), 'the rival connection could not take the lock');
    }

    private function haveTheRivalTry(string $lockName): int
    {
        $acquired = (int) $this->askTheRivalFor('SELECT GET_LOCK(?, 0)', $lockName);

        if (1 === $acquired) {
            $this->locksHeldByTheRival[] = $lockName;
        }

        return $acquired;
    }

    private function askTheRivalFor(string $sql, string $lockName): string
    {
        $statement = $this->rivalConnection()->prepare($sql);
        $statement->bindValue(1, $lockName, \PDO::PARAM_STR);
        $statement->execute();

        return (string) $statement->fetchColumn();
    }

    /**
     * A second connection to the same database, opened from the configuration Propel
     * already holds. Propel hands out one connection per datasource, and a user lock
     * belongs to the connection that took it: asking the very same connection for it
     * would grant it, which is precisely what this test must not do.
     */
    private function rivalConnection(): ConnectionInterface
    {
        if ($this->rivalConnection instanceof ConnectionInterface) {
            return $this->rivalConnection;
        }

        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(OrderHistoryTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        return $this->rivalConnection = ConnectionFactory::create(
            $connectionManager->getConfiguration(),
            $serviceContainer->getAdapter(OrderHistoryTableMap::DATABASE_NAME),
        );
    }
}
