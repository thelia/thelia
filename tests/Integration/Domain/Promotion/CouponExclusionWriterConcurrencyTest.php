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

namespace Thelia\Tests\Integration\Domain\Promotion;

use PHPUnit\Framework\Attributes\Group;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Thelia\Domain\Promotion\Coupon\Exception\InvalidCouponExclusionException;
use Thelia\Domain\Promotion\Coupon\Service\CouponExclusionWriter;
use Thelia\Model\CouponExclusionQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\Map\CouponTableMap;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Integration\Domain\OrderReturn\CompetingRequestTrait;

/**
 * Writing the exclusions of a promotion holds the `coupon` rows of every promotion it touches, the
 * ones it excluded until now included, before anything else: a competing edit of one of them waits
 * for it (and the other way round) instead of crossing it.
 *
 * The competing edit is a second, independent session holding the row, committed for real: the
 * test runs outside the usual test transaction and cleans its fixtures up by hand.
 *
 * Two edits that cross (A excludes B while B excludes A) are run in two processes of their own,
 * each with its own kernel and connection, released at the same moment on rows a third session
 * holds: they queue on the same lock and then really compete. A deadlock that does happen is
 * played with a competing request (see CompetingRequestTrait) that takes the rows in the opposite
 * order and is the heavier transaction, so that the writer is the victim.
 */
#[Group('concurrency')]
final class CouponExclusionWriterConcurrencyTest extends IntegrationTestCase
{
    use CompetingRequestTrait;

    protected bool $useTransaction = false;

    /** How many times each crossing of two edits is played, each time on fresh promotions. */
    private const int ROUNDS = 3;

    private const int PROCESS_TIMEOUT_SECONDS = 60;

    /**
     * The edit, run in a process of its own: boots the kernel, says "ready <connection id>", waits for
     * the go file, writes the exclusions and prints a line of JSON with what happened.
     */
    private const string EDITING_PROCESS = <<<'PHP'
        <?php
        $c = json_decode((string) getenv('EXCLUSION_EDIT'), true, flags: JSON_THROW_ON_ERROR);
        require $c['project'].'/tests/bootstrap.php';

        $kernel = new App\Kernel('test', false);
        $kernel->boot();
        $writer = $kernel->getContainer()->get('test.service_container')->get(Thelia\Domain\Promotion\Coupon\Service\CouponExclusionWriter::class);

        $connection = Propel\Runtime\Propel::getWriteConnection('TheliaMain');
        $connection->exec('SET SESSION innodb_lock_wait_timeout = 20');
        fwrite(STDOUT, 'ready '.(int) $connection->query('SELECT CONNECTION_ID()')->fetchColumn()."\n");
        fflush(STDOUT);

        $deadline = microtime(true) + $c['patience'];
        while (!is_file($c['go']) && microtime(true) < $deadline) {
            usleep(2000);
        }

        try {
            $writer->replace($c['couponId'], $c['excludedCouponIds']);
            $outcome = ['ok' => true];
        } catch (Throwable $throwable) {
            $driverCode = null;
            for ($current = $throwable; null !== $current; $current = $current->getPrevious()) {
                if ($current instanceof PDOException) {
                    $driverCode = (int) ($current->errorInfo[1] ?? 0);
                }
            }
            $outcome = ['ok' => false, 'class' => $throwable::class, 'message' => $throwable->getMessage(), 'driverCode' => $driverCode];
        }

        fwrite(STDOUT, json_encode($outcome, JSON_THROW_ON_ERROR)."\n");
        $kernel->shutdown();
        PHP;

    /** @var list<array{process: resource, pipes: array<int, resource>}> */
    private array $editingProcesses = [];

    private ?string $goFile = null;

    private const LOCK_WAIT_TIMEOUT = 1;

    private FixtureFactory $factory;

    private CouponExclusionWriter $writer;

    private ConnectionInterface $otherSession;

    private ConnectionInterface $mainConnection;

    private string $previousLockWaitTimeout = '50';

    private int $lastCouponIdBefore = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lastCouponIdBefore = (int) CouponQuery::create()->orderById(Criteria::DESC)->findOne()?->getId();
        $this->factory = $this->createFixtureFactory();
        $this->writer = $this->getService(CouponExclusionWriter::class);

        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(CouponTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        $this->otherSession = ConnectionFactory::create($connectionManager->getConfiguration(), $serviceContainer->getAdapter(CouponTableMap::DATABASE_NAME));
        $this->mainConnection = Propel::getWriteConnection(CouponTableMap::DATABASE_NAME);
        $this->previousLockWaitTimeout = (string) $this->mainConnection->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn();
        $this->mainConnection->exec('SET SESSION innodb_lock_wait_timeout = '.self::LOCK_WAIT_TIMEOUT);
    }

    protected function tearDown(): void
    {
        try {
            $this->stopCompetingRequest();
            $this->stopEditingProcesses();
            while ($this->otherSession->inTransaction()) {
                $this->otherSession->rollBack();
            }

            while ($this->mainConnection->inTransaction()) {
                $this->mainConnection->rollBack();
            }

            $this->mainConnection->exec('SET SESSION innodb_lock_wait_timeout = '.(int) $this->previousLockWaitTimeout);
            CouponQuery::create()->filterById($this->lastCouponIdBefore, Criteria::GREATER_THAN)->delete();
        } finally {
            parent::tearDown();
        }
    }

    public function testAnEditWaitsForAPromotionThatTheEditedOneExcludedUntilNow(): void
    {
        $edited = $this->factory->coupon()->getId();
        $formerPartner = $this->factory->coupon()->getId();
        $this->writer->replace($edited, [$formerPartner]);

        // Another admin is saving the former partner: it holds its row.
        $this->otherSession->beginTransaction();
        $this->otherSession->query('SELECT `id` FROM `coupon` WHERE `id` = '.$formerPartner.' FOR UPDATE')->fetchAll();

        try {
            $this->writer->replace($edited, []);
            self::fail('Removing the pair must wait for the row of the former partner, held by the other edit.');
        } catch (\Throwable $throwable) {
            self::assertTrue(self::isLockWaitTimeout($throwable), 'Expected a lock wait timeout (1205), got '.$throwable::class.': '.$throwable->getMessage());
        }

        self::assertSame([$formerPartner], CouponExclusionQuery::create()->excludedCouponIdsFor($edited), 'Nothing was written.');

        $this->otherSession->rollBack();
        $this->writer->replace($edited, []);

        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($edited));
        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($formerPartner));
    }

    public function testAnEditWaitsForAPromotionItIsAboutToExclude(): void
    {
        $edited = $this->factory->coupon()->getId();
        $newPartner = $this->factory->coupon()->getId();

        $this->otherSession->beginTransaction();
        $this->otherSession->query('SELECT `id` FROM `coupon` WHERE `id` = '.$newPartner.' FOR UPDATE')->fetchAll();

        try {
            $this->writer->replace($edited, [$newPartner]);
            self::fail('Excluding the promotion must wait for its row, held by the other edit.');
        } catch (\Throwable $throwable) {
            self::assertTrue(self::isLockWaitTimeout($throwable), 'Expected a lock wait timeout (1205), got '.$throwable::class.': '.$throwable->getMessage());
        }

        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($edited));
    }

    public function testTheRowsToLockAreThePromotionTheNewAndTheFormerPartnersByIncreasingId(): void
    {
        $edited = $this->factory->coupon()->getId();
        $former = $this->factory->coupon()->getId();
        $new = $this->factory->coupon()->getId();
        $this->writer->replace($edited, [$former]);

        self::assertSame([$edited, $former, $new], $this->writer->rowsToLock($edited, [$new]));
        self::assertSame([$new], $this->writer->rowsToLock(null, [$new]));
    }

    public function testTwoEditsThatExcludeEachOtherAtTheSameTimeLeaveTheSymmetricPair(): void
    {
        for ($round = 0; $round < self::ROUNDS; ++$round) {
            $a = $this->factory->coupon()->getId();
            $b = $this->factory->coupon()->getId();

            $outcomes = $this->editAtTheSameTime([[$a, [$b]], [$b, [$a]]], [$a, $b]);

            $this->assertTheEditsLeftASerialOutcome([[$a, [$b]], [$b, [$a]]], $outcomes, [$a, $b]);
        }
    }

    public function testTwoEditsThatShareAPromotionAtTheSameTimeLeaveASerialOutcome(): void
    {
        for ($round = 0; $round < self::ROUNDS; ++$round) {
            $a = $this->factory->coupon()->getId();
            $b = $this->factory->coupon()->getId();
            $c = $this->factory->coupon()->getId();

            $outcomes = $this->editAtTheSameTime([[$a, [$b]], [$b, [$c]]], [$a, $b, $c]);

            $this->assertTheEditsLeftASerialOutcome([[$a, [$b]], [$b, [$c]]], $outcomes, [$a, $b, $c]);
        }
    }

    public function testAnEditThatIsTheVictimOfADeadlockIsRefusedAsAConcurrentEdit(): void
    {
        $low = $this->factory->coupon()->getId();
        $high = $this->factory->coupon()->getId();
        self::assertLessThan($high, $low);

        $this->mainConnection->exec('SET SESSION innodb_lock_wait_timeout = 20');
        $threadId = (int) $this->mainConnection->query('SELECT CONNECTION_ID()')->fetchColumn();

        // The other edit holds the higher row. The writer takes the lower one, then waits for the
        // higher one: once seen waiting, the other edit asks for the lower one, a deadlock. It is the
        // heavier transaction (ballast), so the database gives up on the writer.
        $this->startCompetingRequest($threadId, [['lock', 'coupon', $high]], [['ballast', 300], ['lock', 'coupon', $low]], false);

        try {
            $this->writer->replace($low, [$high]);
            self::fail('The writer was expected to be the victim of the deadlock.');
        } catch (InvalidCouponExclusionException $refusal) {
            $driver = null;

            for ($current = $refusal->getPrevious(); null !== $current; $current = $current->getPrevious()) {
                if ($current instanceof \PDOException) {
                    $driver = (int) ($current->errorInfo[1] ?? 0);
                }
            }

            self::assertSame(1213, $driver, 'The refusal must come from the deadlock: '.$refusal->getMessage());
            self::assertStringContainsString('try again', $refusal->getMessage());
        } catch (\Throwable $throwable) {
            self::fail('A deadlock must be refused as InvalidCouponExclusionException, got '.$throwable::class.': '.$throwable->getMessage());
        }

        $this->finishCompetingRequest();

        self::assertFalse($this->mainConnection->inTransaction(), 'The writer left its transaction open.');
        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($low), 'Nothing was written.');
        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($high));

        // The refusal is one to retry: the same edit goes through once the other one is over.
        $this->writer->replace($low, [$high]);
        self::assertSame([$high], CouponExclusionQuery::create()->excludedCouponIdsFor($low));
        self::assertSame([$low], CouponExclusionQuery::create()->excludedCouponIdsFor($high));
    }

    public function testOnlyAConflictOfTheDatabaseIsRefusedAsAConcurrentEdit(): void
    {
        $translate = new \ReflectionMethod($this->writer, 'translate');

        foreach ([1213, 1020] as $driverCode) {
            $conflict = new \PDOException('conflict');
            $conflict->errorInfo = ['40001', $driverCode, 'conflict'];
            $wrapped = new \RuntimeException('Unable to execute statement', 0, $conflict);

            $translated = $translate->invoke($this->writer, $wrapped);

            self::assertInstanceOf(InvalidCouponExclusionException::class, $translated, 'Driver code '.$driverCode);
            self::assertSame($wrapped, $translated->getPrevious());
        }

        $timeout = new \PDOException('lock wait timeout');
        $timeout->errorInfo = ['HY000', 1205, 'lock wait timeout'];
        self::assertSame($timeout, $translate->invoke($this->writer, $timeout), 'A lock wait timeout is not a conflict to retry.');

        $other = new \RuntimeException('boom');
        self::assertSame($other, $translate->invoke($this->writer, $other));
    }

    /**
     * Runs the edits, each in a process of its own, released together while a third session holds the
     * rows of every promotion they touch: the edits queue on those rows and compete when released.
     *
     * @param list<array{0: int, 1: list<int>}> $edits
     * @param list<int>                         $touched
     *
     * @return list<array{ok: bool, class?: string, message?: string, driverCode?: int|null}>
     */
    private function editAtTheSameTime(array $edits, array $touched): array
    {
        sort($touched);
        $this->goFile = (string) tempnam(sys_get_temp_dir(), 'exclusion-edit-go-');
        unlink($this->goFile);

        $this->otherSession->beginTransaction();

        foreach ($touched as $couponId) {
            $this->otherSession->query('SELECT `id` FROM `coupon` WHERE `id` = '.$couponId.' FOR UPDATE')->fetchAll();
        }

        $threadIds = [];

        foreach ($edits as [$couponId, $excludedCouponIds]) {
            $threadIds[] = $this->startEditingProcess($couponId, $excludedCouponIds);
        }

        touch($this->goFile);
        $bothQueued = $this->waitUntilQueuedOnTheRows($threadIds);
        $this->otherSession->rollBack();

        $outcomes = [];

        foreach ($this->editingProcesses as $editing) {
            $outcomes[] = $this->outcomeOf($editing);
        }

        $this->stopEditingProcesses();

        self::assertTrue($bothQueued, 'The edits were never both seen waiting for the rows the third session held, so they did not compete: the test proved nothing.');

        return $outcomes;
    }

    /**
     * @param list<int> $excludedCouponIds
     */
    private function startEditingProcess(int $couponId, array $excludedCouponIds): int
    {
        $process = proc_open(
            [\PHP_BINARY],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            [...getenv(), 'EXCLUSION_EDIT' => json_encode([
                'project' => \dirname(__DIR__, 4),
                'go' => $this->goFile,
                'couponId' => $couponId,
                'excludedCouponIds' => $excludedCouponIds,
                'patience' => self::PROCESS_TIMEOUT_SECONDS,
            ], \JSON_THROW_ON_ERROR)],
        );
        self::assertIsResource($process, 'The editing process could not be started.');

        $this->editingProcesses[] = ['process' => $process, 'pipes' => $pipes];
        fwrite($pipes[0], self::EDITING_PROCESS);
        fclose($pipes[0]);

        $line = $this->readLine($pipes[1]);

        if (null === $line || 1 !== preg_match('/^ready \d+$/', $line)) {
            // Only now: reading to the end of a pipe waits for the process to end.
            self::fail('The editing process did not get ready: '.$line.stream_get_contents($pipes[1]).stream_get_contents($pipes[2]));
        }

        return (int) substr($line, 6);
    }

    /**
     * @param list<int> $threadIds
     */
    private function waitUntilQueuedOnTheRows(array $threadIds): bool
    {
        $waiting = $this->otherSession->prepare(
            'SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID IN ('.implode(', ', $threadIds).") AND COMMAND IN ('Query', 'Execute') AND INFO LIKE '%FOR UPDATE%'"
        );
        $deadline = microtime(true) + 10;
        $seenFor = 0;

        while ($seenFor < 10 && microtime(true) < $deadline) {
            $waiting->execute();
            $seenFor = (int) $waiting->fetchColumn() === \count($threadIds) ? $seenFor + 1 : 0;
            $waiting->closeCursor();
            usleep(20000);
        }

        return $seenFor >= 10;
    }

    /**
     * @param array{process: resource, pipes: array<int, resource>} $editing
     *
     * @return array{ok: bool, class?: string, message?: string, driverCode?: int|null}
     */
    private function outcomeOf(array $editing): array
    {
        $line = $this->readLine($editing['pipes'][1]);
        $decoded = null === $line ? null : json_decode($line, true);

        if (!\is_array($decoded)) {
            self::fail('The editing process gave no outcome: '.$line.stream_get_contents($editing['pipes'][2]));
        }

        return $decoded;
    }

    /**
     * @param resource $pipe
     */
    private function readLine($pipe): ?string
    {
        // Not stream_select(): a line already read into the buffer of PHP, along with the one before it, is invisible to it.
        stream_set_timeout($pipe, self::PROCESS_TIMEOUT_SECONDS);
        $line = fgets($pipe);

        return false === $line ? null : rtrim($line);
    }

    private function stopEditingProcesses(): void
    {
        foreach ($this->editingProcesses as $editing) {
            foreach ($editing['pipes'] as $pipe) {
                if (\is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            $status = proc_get_status($editing['process']);

            if ($status['running']) {
                proc_terminate($editing['process']);
            }

            proc_close($editing['process']);
        }

        $this->editingProcesses = [];

        if (null !== $this->goFile && is_file($this->goFile)) {
            unlink($this->goFile);
        }

        $this->goFile = null;
    }

    /**
     * No server error, only a refusal to retry at worst; and what is stored is what one of the
     * orders in which the edits that went through could have run one after the other leaves: every
     * pair in both directions or in none, nothing half written.
     *
     * @param list<array{0: int, 1: list<int>}>                                              $edits
     * @param list<array{ok: bool, class?: string, message?: string, driverCode?: int|null}> $outcomes
     * @param list<int>                                                                      $touched
     */
    private function assertTheEditsLeftASerialOutcome(array $edits, array $outcomes, array $touched): void
    {
        $succeeded = [];

        foreach ($outcomes as $index => $outcome) {
            if (!$outcome['ok']) {
                self::assertSame(InvalidCouponExclusionException::class, $outcome['class'] ?? null, 'An edit failed with a server error: '.json_encode($outcome));
                continue;
            }

            $succeeded[] = $edits[$index];
        }

        self::assertNotSame([], $succeeded, 'At least one of the edits must go through: '.json_encode($outcomes));

        $stored = $this->storedPairs($touched);

        foreach ($stored as $pair) {
            [$from, $to] = explode('>', $pair);
            self::assertContains($to.'>'.$from, $stored, 'The pair '.$pair.' is written in one direction only.');
        }

        $allowed = [];

        foreach ($this->permutations($succeeded) as $order) {
            $pairs = [];

            foreach ($order as [$couponId, $excludedCouponIds]) {
                $pairs = array_filter($pairs, static fn (string $pair): bool => !\in_array((string) $couponId, explode('>', $pair), true));

                foreach ($excludedCouponIds as $excludedCouponId) {
                    $pairs[] = $couponId.'>'.$excludedCouponId;
                    $pairs[] = $excludedCouponId.'>'.$couponId;
                }
            }

            sort($pairs);
            $allowed[] = array_values(array_unique($pairs));
        }

        self::assertContains($stored, $allowed, 'Stored '.json_encode($stored).', none of the serial outcomes '.json_encode($allowed));
    }

    /**
     * @param list<int> $couponIds
     *
     * @return list<string> "from>to", sorted
     */
    private function storedPairs(array $couponIds): array
    {
        $in = implode(', ', $couponIds);
        $rows = $this->otherSession
            ->query('SELECT `coupon_id`, `excluded_coupon_id` FROM `coupon_exclusion` WHERE `coupon_id` IN ('.$in.') OR `excluded_coupon_id` IN ('.$in.')')
            ->fetchAll(\PDO::FETCH_NUM);
        $pairs = array_map(static fn (array $row): string => $row[0].'>'.$row[1], $rows);
        sort($pairs);

        return $pairs;
    }

    /**
     * @template T
     *
     * @param list<T> $items
     *
     * @return list<list<T>>
     */
    private function permutations(array $items): array
    {
        if (\count($items) <= 1) {
            return [$items];
        }

        $permutations = [];

        foreach ($items as $index => $item) {
            $rest = $items;
            unset($rest[$index]);

            foreach ($this->permutations(array_values($rest)) as $permutation) {
                $permutations[] = [$item, ...$permutation];
            }
        }

        return $permutations;
    }

    /**
     * What CompetingRequestTrait needs: the configuration of the test database.
     *
     * @return array{dsn: string, user?: string, password?: string}
     */
    private function secondConnectionConfiguration(): array
    {
        $connectionManager = Propel::getServiceContainer()->getConnectionManager(CouponTableMap::DATABASE_NAME);
        self::assertInstanceOf(ConnectionManagerSingle::class, $connectionManager);

        return $connectionManager->getConfiguration();
    }

    private static function isLockWaitTimeout(\Throwable $throwable): bool
    {
        for ($current = $throwable; null !== $current; $current = $current->getPrevious()) {
            if ($current instanceof \PDOException && 1205 === (int) ($current->errorInfo[1] ?? 0)) {
                return true;
            }
        }

        return false;
    }
}
