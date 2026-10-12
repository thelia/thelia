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

namespace Thelia\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\DataFetcher\PDODataFetcher;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\TheliaKernel;
use Thelia\Log\Tlog;

/**
 * The server engine is faked through VERSION(), so both the MySQL and the
 * MariaDB branch are exercised whatever the database the suite runs against.
 */
final class TheliaKernelSqlModeTest extends TestCase
{
    private const SERVER_QUERY = 'SELECT VERSION() as version, @@GLOBAL.sql_mode as global_sql_mode';

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/thelia-sql-mode-'.bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($this->cacheDir);

        // Tlog::getInstance() would read its configuration from the database.
        // A bare instance keeps its default ERROR level, so warnings are dropped.
        $this->setTlogInstance((new \ReflectionClass(Tlog::class))->newInstanceWithoutConstructor());
    }

    protected function tearDown(): void
    {
        $this->setTlogInstance(null);
        (new Filesystem())->remove($this->cacheDir);
    }

    public function testMariaDbKeepsStrictTransTablesAndEveryOtherMode(): void
    {
        $serverModes = ['STRICT_TRANS_TABLES', 'ERROR_FOR_DIVISION_BY_ZERO', 'NO_AUTO_CREATE_USER', 'NO_ENGINE_SUBSTITUTION'];

        $modes = $this->resolveSessionSqlMode('10.11.11-MariaDB-log', $serverModes);

        self::assertContains('STRICT_TRANS_TABLES', $modes);
        self::assertSame($serverModes, $modes);
    }

    public function testMySqlKeepsStrictTransTablesAndOnlyDropsOnlyFullGroupBy(): void
    {
        $serverModes = ['ONLY_FULL_GROUP_BY', 'STRICT_TRANS_TABLES', 'NO_ZERO_IN_DATE', 'NO_ZERO_DATE', 'ERROR_FOR_DIVISION_BY_ZERO', 'NO_ENGINE_SUBSTITUTION'];

        $modes = $this->resolveSessionSqlMode('8.0.40', $serverModes);

        self::assertContains('STRICT_TRANS_TABLES', $modes);
        self::assertNotContains('ONLY_FULL_GROUP_BY', $modes);
        self::assertSame(array_values(array_diff($serverModes, ['ONLY_FULL_GROUP_BY'])), $modes);
    }

    /**
     * Emptying the cache directory leaves workers that still believe the verdict
     * file is there, and reading it then killed the boot: every back-office action
     * clearing the cache answered 500 until the worker recycled. An unreadable file
     * puts the kernel in that same position, without a worker to recycle.
     */
    public function testAnUnreadableVerdictIsRebuiltInsteadOfBreakingTheBoot(): void
    {
        $verdictFile = $this->cacheDir.'/check_mysql_configurations.php';
        file_put_contents($verdictFile, '<?php return '.var_export(['modes' => [], 'canUpdate' => false, 'logs' => []], true).';');
        chmod($verdictFile, 0o000);

        if (is_readable($verdictFile)) {
            self::markTestSkipped('The test user reads the file whatever its mode.');
        }

        $modes = $this->resolveSessionSqlMode('8.0.40', ['ONLY_FULL_GROUP_BY', 'STRICT_TRANS_TABLES']);

        self::assertNotContains('ONLY_FULL_GROUP_BY', $modes);
        self::assertContains('STRICT_TRANS_TABLES', $modes);
    }

    public function testAServerNeedingNoCorrectionIsLeftAlone(): void
    {
        self::assertSame(
            [self::SERVER_QUERY],
            $this->statementsSent('10.11.11-MariaDB-log', 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'),
        );
    }

    public function testMariaDbMissingNoEngineSubstitutionGetsItForTheSession(): void
    {
        self::assertSame(
            [self::SERVER_QUERY, "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';"],
            $this->statementsSent('10.11.11-MariaDB-log', 'STRICT_TRANS_TABLES'),
        );
    }

    public function testAnEmptyServerSqlModeOnlyGainsNoEngineSubstitution(): void
    {
        self::assertSame(
            [self::SERVER_QUERY, "SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION';"],
            $this->statementsSent('10.11.11-MariaDB-log', ''),
        );
    }

    public function testMySqlMissingNoEngineSubstitutionGetsItForTheSession(): void
    {
        self::assertSame(
            [self::SERVER_QUERY, "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';"],
            $this->statementsSent('8.0.40', 'STRICT_TRANS_TABLES'),
        );
    }

    public function testKernelsSharingACacheDirectoryAskTheServerOnce(): void
    {
        $setSession = "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';";

        self::assertSame(
            [self::SERVER_QUERY, $setSession, $setSession],
            $this->statementsSent('8.0.40', 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES', kernels: 2),
        );
    }

    public function testAVerdictInTheCacheDirectoryIsAppliedWithoutAskingTheServer(): void
    {
        $this->writeVerdict(['modes' => ['STRICT_TRANS_TABLES', 'NO_ENGINE_SUBSTITUTION'], 'canUpdate' => true, 'logs' => []]);

        self::assertSame(
            ["SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';"],
            $this->statementsSent('8.0.40', 'ONLY_FULL_GROUP_BY'),
        );
    }

    /**
     * A verdict written by an earlier version may lack an entry: the server is
     * asked again rather than the session being set from half a verdict.
     */
    public function testAnIncompleteVerdictIsProbedAgain(): void
    {
        $this->writeVerdict(['modes' => ['ONLY_FULL_GROUP_BY'], 'canUpdate' => true]);

        self::assertSame(
            [self::SERVER_QUERY, "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';"],
            $this->statementsSent('8.0.40', 'STRICT_TRANS_TABLES'),
        );
    }

    /**
     * @param array<string, mixed> $verdict
     */
    private function writeVerdict(array $verdict): void
    {
        file_put_contents(
            $this->cacheDir.'/check_mysql_configurations.php',
            '<?php return '.var_export($verdict, true).';',
        );
    }

    /**
     * @return list<string> every statement the kernels sent, in order
     */
    private function statementsSent(string $version, string $globalSqlMode, int $kernels = 1): array
    {
        $fetcher = $this->createMock(PDODataFetcher::class);
        $fetcher->method('fetch')->willReturn(['version' => $version, 'global_sql_mode' => $globalSqlMode]);

        $statements = [];

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('query')->willReturnCallback(
            static function (string $statement) use ($fetcher, &$statements) {
                $statements[] = $statement;

                return $fetcher;
            }
        );

        for ($booted = 0; $booted < $kernels; ++$booted) {
            $kernel = new class($this->cacheDir) extends TheliaKernel {
                public function __construct(private readonly string $sqlModeCacheDir)
                {
                }

                public function getCacheDir(): string
                {
                    return $this->sqlModeCacheDir;
                }

                public function resolveSqlMode(ConnectionInterface $con): void
                {
                    $this->checkMySQLConfigurations($con);
                }
            };

            $kernel->resolveSqlMode($connection);
        }

        return $statements;
    }

    /**
     * @param string[] $serverModes
     *
     * @return string[] the sql_mode the kernel settles on for the session
     */
    private function resolveSessionSqlMode(string $version, array $serverModes): array
    {
        $fetcher = $this->createMock(PDODataFetcher::class);
        $fetcher->method('fetch')->willReturn([
            'version' => $version,
            'global_sql_mode' => implode(',', $serverModes),
        ]);

        $appliedModes = $serverModes;

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('query')->willReturnCallback(
            static function (string $statement) use ($fetcher, &$appliedModes) {
                if (str_starts_with($statement, 'SELECT VERSION()')) {
                    return $fetcher;
                }

                self::assertSame(1, preg_match("/^SET SESSION sql_mode='(.*)';$/", $statement, $matches));
                $appliedModes = explode(',', $matches[1]);

                return $fetcher;
            }
        );

        $kernel = new class($this->cacheDir) extends TheliaKernel {
            public function __construct(private readonly string $sqlModeCacheDir)
            {
            }

            public function getCacheDir(): string
            {
                return $this->sqlModeCacheDir;
            }

            public function resolveSqlMode(ConnectionInterface $con): void
            {
                $this->checkMySQLConfigurations($con);
            }
        };

        $kernel->resolveSqlMode($connection);

        return $appliedModes;
    }

    private function setTlogInstance(?Tlog $instance): void
    {
        $property = new \ReflectionProperty(Tlog::class, 'instance');
        $property->setValue(null, $instance);
    }
}
