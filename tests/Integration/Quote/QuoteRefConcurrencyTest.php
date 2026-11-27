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

namespace Thelia\Tests\Integration\Quote;

use PHPUnit\Framework\Attributes\Group;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Thelia\Domain\Quote\Service\QuoteRefAllocator;
use Thelia\Domain\Sequence\GaplessSequenceGenerator;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The references of the quotes allocated by two requests at once: one number each, none twice,
 * none lost.
 *
 * The competing request is a second PHP process with its own kernel and its own connection,
 * started while this one holds the number it took and has not committed. The series is set to a
 * known value, committed, and put back as it was.
 */
#[Group('concurrency')]
final class QuoteRefConcurrencyTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    private const int PROCESS_TIMEOUT = 60;

    private ?ConnectionInterface $otherSession = null;

    /** The value of the series before the test, null when the series did not exist. */
    private ?int $seriesBefore = null;

    /** @var array<int, array<int, resource>> */
    private array $pipes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->otherSession = $this->openOtherSession();
        $current = Propel::getWriteConnection(QuoteTableMap::DATABASE_NAME)
            ->query("SELECT `current_value` FROM `order_sequence` WHERE `name` = 'quote_ref'")
            ->fetchColumn();
        $this->seriesBefore = \in_array($current, [false, null, ''], true) ? null : (int) $current;

        $this->getService(GaplessSequenceGenerator::class)->set(QuoteRefAllocator::SEQUENCE_NAME, 100, Propel::getWriteConnection(QuoteTableMap::DATABASE_NAME));
    }

    protected function tearDown(): void
    {
        try {
            $connection = Propel::getWriteConnection(QuoteTableMap::DATABASE_NAME);

            while ($connection->inTransaction()) {
                $connection->rollBack();
            }

            if (null === $this->seriesBefore) {
                $connection->exec("DELETE FROM `order_sequence` WHERE `name` = 'quote_ref'");
            } else {
                $this->getService(GaplessSequenceGenerator::class)->set(QuoteRefAllocator::SEQUENCE_NAME, $this->seriesBefore, $connection);
            }
        } finally {
            foreach ($this->pipes as $pipes) {
                foreach ($pipes as $pipe) {
                    if (\is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
            }

            parent::tearDown();
        }
    }

    public function testTheOtherProcessWorksOnTheDatabaseOfThisProcess(): void
    {
        self::assertSame(
            (string) Propel::getWriteConnection(QuoteTableMap::DATABASE_NAME)->query('SELECT DATABASE()')->fetchColumn(),
            (string) $this->otherSession?->query('SELECT DATABASE()')->fetchColumn(),
        );

        $outcome = $this->waitFor($this->allocateInAnotherProcess());

        self::assertSame('QUO00000101', $outcome['ref'], 'the other process reads and moves the series of this one');
    }

    public function testAnAllocationWaitsForTheOneThatIsNotCommittedAndTakesTheNextNumber(): void
    {
        $connection = Propel::getWriteConnection(QuoteTableMap::DATABASE_NAME);
        $connection->beginTransaction();
        $first = $this->getService(QuoteRefAllocator::class)->allocate($connection);
        self::assertSame('QUO00000101', $first);

        $process = $this->allocateInAnotherProcess();
        $this->waitUntilTheOtherProcessWaitsOnTheSeries($process);
        $connection->commit();

        self::assertSame('QUO00000102', $this->waitFor($process)['ref']);
        self::assertSame(102, $this->seriesValue());
    }

    public function testANumberThatWasRolledBackIsGivenToTheNextQuoteWithNoGap(): void
    {
        $connection = Propel::getWriteConnection(QuoteTableMap::DATABASE_NAME);
        $connection->beginTransaction();
        self::assertSame('QUO00000101', $this->getService(QuoteRefAllocator::class)->allocate($connection));

        $process = $this->allocateInAnotherProcess();
        $this->waitUntilTheOtherProcessWaitsOnTheSeries($process);
        $connection->rollBack();

        self::assertSame('QUO00000101', $this->waitFor($process)['ref'], 'the number of the quote that was not written is not lost');
        self::assertSame(101, $this->seriesValue());
    }

    public function testTwoProcessesAtOnceTakeTwoConsecutiveNumbers(): void
    {
        $first = $this->allocateInAnotherProcess();
        $second = $this->allocateInAnotherProcess();

        $refs = [$this->waitFor($first)['ref'], $this->waitFor($second)['ref']];
        sort($refs);

        self::assertSame(['QUO00000101', 'QUO00000102'], $refs);
        self::assertSame(102, $this->seriesValue());
    }

    private function seriesValue(): int
    {
        return (int) Propel::getWriteConnection(QuoteTableMap::DATABASE_NAME)
            ->query("SELECT `current_value` FROM `order_sequence` WHERE `name` = 'quote_ref'")
            ->fetchColumn();
    }

    /**
     * @return resource
     */
    private function allocateInAnotherProcess(): mixed
    {
        $process = proc_open(
            [\PHP_BINARY, __DIR__.'/Support/allocate-quote-ref-in-another-process.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        $this->pipes[(int) $process] = $pipes;

        return $process;
    }

    /**
     * @param resource $process
     */
    private function waitUntilTheOtherProcessWaitsOnTheSeries($process): void
    {
        $ownThreads = [
            (int) Propel::getWriteConnection(QuoteTableMap::DATABASE_NAME)->query('SELECT CONNECTION_ID()')->fetchColumn(),
            (int) $this->otherSession?->query('SELECT CONNECTION_ID()')->fetchColumn(),
        ];
        $waiting = $this->otherSession?->prepare(
            "SELECT ID FROM information_schema.PROCESSLIST WHERE DB = DATABASE() AND COMMAND IN ('Query', 'Execute') AND INFO LIKE 'INSERT INTO `order_sequence`%' AND ID NOT IN (".implode(',', $ownThreads).')',
        );
        self::assertNotNull($waiting);
        $deadline = microtime(true) + self::PROCESS_TIMEOUT;
        $seen = [];

        do {
            if (!proc_get_status($process)['running']) {
                self::fail('The other process ended before waiting: '.stream_get_contents($this->pipes[(int) $process][2]));
            }

            $waiting->execute();
            $threads = $waiting->fetchAll(\PDO::FETCH_COLUMN);
            $waiting->closeCursor();

            // Seen twice in a row on the same statement: it is waiting, not passing by.
            if ([] !== array_intersect($threads, $seen)) {
                return;
            }

            $seen = $threads;
            usleep(200000);
        } while (microtime(true) < $deadline);

        self::fail('The other process never waited on the series.');
    }

    /**
     * @param resource $process
     *
     * @return array{ref: string}
     */
    private function waitFor($process): array
    {
        $stdout = $this->pipes[(int) $process][1];
        $stderr = $this->pipes[(int) $process][2];
        unset($this->pipes[(int) $process]);
        $output = stream_get_contents($stdout);
        $errors = stream_get_contents($stderr);
        fclose($stdout);
        fclose($stderr);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, 'The other process failed: '.$errors.$output);

        // The JSON is the last thing the process prints, after whatever booting it wrote.
        return json_decode(substr((string) $output, (int) strrpos((string) $output, '{"ref"')), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function openOtherSession(): ConnectionInterface
    {
        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(QuoteTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        return ConnectionFactory::create(
            $connectionManager->getConfiguration(),
            $serviceContainer->getAdapter(QuoteTableMap::DATABASE_NAME),
        );
    }
}
