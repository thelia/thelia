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

namespace Thelia\Tests\Integration\Domain\OrderReturn;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\Exception\ReturnRequestConflictException;
use Thelia\Domain\OrderReturn\Service\OrderReturnWriteTransaction;
use Thelia\Domain\OrderReturn\Service\OrderReturnWriteTransactionInterface;
use Thelia\Test\IntegrationTestCase;

/**
 * What the transaction a return is written in does around the work it runs,
 * whatever that work is. The rows it locks and the order it locks them in are
 * proven against a competing session in Thelia\Tests\Api\OrderReturnConcurrencyTest.
 *
 * Outside the test transaction, so that run() is the outermost transaction,
 * as in a request. The ids name no row: nothing is written.
 */
final class OrderReturnWriteTransactionTest extends IntegrationTestCase
{
    private const int NO_ORDER = 2147483000;
    private const int NO_LINE = 2147483001;

    protected bool $useTransaction = false;

    private OrderReturnWriteTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transaction = $this->getService(OrderReturnWriteTransaction::class);
    }

    public function testTheContainerHandsOutOneInstanceForTheInterfaceAndTheClass(): void
    {
        // The eligibility checker reads readsSeeCommits() on the instance the
        // processors run their work in: two instances would make every check
        // fall back to locking reads.
        self::assertSame($this->transaction, $this->getService(OrderReturnWriteTransactionInterface::class));
    }

    public function testTheWorkRunsInATransactionWhosePlainReadsMayBeTrusted(): void
    {
        $result = $this->transaction->run(self::NO_ORDER, [self::NO_LINE], function (): string {
            self::assertTrue($this->connection()->inTransaction());
            self::assertTrue($this->transaction->readsSeeCommits());

            return 'done';
        });

        self::assertSame('done', $result);
        self::assertFalse($this->connection()->inTransaction(), 'The transaction was left open.');
        self::assertFalse($this->transaction->readsSeeCommits());
    }

    public function testNestedInACallerTransactionTheChecksFallBackToLockingReads(): void
    {
        $this->connection()->beginTransaction();

        try {
            $this->transaction->run(self::NO_ORDER, [], function (): void {
                self::assertFalse($this->transaction->readsSeeCommits());
            });

            self::assertTrue($this->connection()->inTransaction(), 'A nested run() ended the transaction of its caller.');
        } finally {
            $this->connection()->rollBack();
        }
    }

    public function testARefusalRollsTheTransactionBackAndPassesThrough(): void
    {
        try {
            $this->transaction->run(self::NO_ORDER, [], static function (): never {
                throw new ReturnNotAllowedException('Refused.');
            });
            self::fail('The refusal did not reach the caller.');
        } catch (ReturnNotAllowedException $exception) {
            self::assertNotInstanceOf(ReturnRequestConflictException::class, $exception);
            self::assertSame('Refused.', $exception->getMessage());
        }

        self::assertFalse($this->connection()->inTransaction(), 'The transaction of the refused request was left open.');
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function lockConflicts(): iterable
    {
        yield 'deadlock' => [1213];
        yield 'row changed since the picture (MariaDB 11.6 and later)' => [1020];
    }

    /**
     * The database breaking a lock conflict is not a failure of the shop: the
     * request is refused with a message that invites a retry, nothing written.
     */
    #[DataProvider('lockConflicts')]
    public function testALockConflictComesBackAsARetryableRefusal(int $driverCode): void
    {
        try {
            $this->transaction->run(self::NO_ORDER, [self::NO_LINE], static function () use ($driverCode): never {
                $conflict = new \PDOException('SQLSTATE: lock conflict');
                $conflict->errorInfo = ['40001', $driverCode, 'lock conflict'];

                throw new PropelException('Unable to execute statement', 0, $conflict);
            });
            self::fail('The lock conflict was not turned into a refusal.');
        } catch (ReturnRequestConflictException $exception) {
            self::assertSame(ReturnRequestConflictException::MESSAGE, $exception->getMessage());
        }

        self::assertFalse($this->connection()->inTransaction(), 'The transaction of the refused request was left open.');
    }

    public function testAnotherDatabaseErrorIsNotAConflict(): void
    {
        $failure = new \PDOException('SQLSTATE: lock wait timeout');
        $failure->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded'];

        try {
            $this->transaction->run(self::NO_ORDER, [], static function () use ($failure): never {
                throw $failure;
            });
            self::fail('The database error did not reach the caller.');
        } catch (\PDOException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    /**
     * A transaction opened on the PDO connection behind Propel's back would
     * make the beginTransaction() of the return a no-op, and the checks would
     * run in no transaction of theirs. That is refused rather than guessed.
     */
    public function testATransactionOpenBehindPropelsBackIsRefused(): void
    {
        $connection = $this->connection();
        self::assertInstanceOf(ConnectionWrapper::class, $connection);

        $pdo = $connection->getWrappedConnection();
        $pdo->beginTransaction();

        try {
            $this->transaction->run(self::NO_ORDER, [], static fn (): null => null);
            self::fail('The return transaction ran over a transaction Propel does not know about.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('Propel counts none', $exception->getMessage());
        } finally {
            $pdo->rollBack();
        }
    }

    private function connection(): ConnectionWrapper
    {
        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        self::assertInstanceOf(ConnectionWrapper::class, $connection);

        return $connection;
    }
}
