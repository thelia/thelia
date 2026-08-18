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
