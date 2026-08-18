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

namespace Thelia\Tests\Integration\Core;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\TheliaKernel;
use Thelia\Test\IntegrationTestCase;

/**
 * The sql_mode verdict is cached across requests, so it must describe the
 * server configuration (what a fresh connection inherits), never the current
 * session: bin/install boots several kernels over one shared connection, and
 * a session already corrected by a previous kernel would otherwise cache a
 * "nothing to do" verdict that every later web request inherits.
 *
 * The test puts the current session in the state OPPOSITE to the server
 * configuration (whichever direction that is on the engine at hand), then
 * checks that the cached verdict still reflects the server configuration.
 */
final class TheliaKernelSqlModeVerdictTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    public function testVerdictReflectsServerConfigurationNotCurrentSession(): void
    {
        $con = $this->getPropelConnection();

        $row = $con
            ->query('SELECT VERSION() AS version, @@GLOBAL.sql_mode AS global_mode, @@SESSION.sql_mode AS session_mode')
            ->fetch(\PDO::FETCH_ASSOC);

        $isMariaDb = str_contains((string) $row['version'], 'MariaDB');
        $globalModes = $this->parseModes((string) $row['global_mode']);
        $correctedModes = $this->applyTheliaCorrections($globalModes, $isMariaDb);
        $serverNeedsCorrection = $correctedModes !== $globalModes;

        // Diverge the session from the server configuration, flipping the
        // verdict a session-based check would reach: a server that needs
        // correcting gets an already-corrected session (the bin/install
        // scenario), a compliant server gets a session that needs correcting.
        $divergentSessionModes = $serverNeedsCorrection
            ? $correctedModes
            : array_values(array_diff($globalModes, ['NO_ENGINE_SUBSTITUTION']));

        $originalSessionMode = (string) $row['session_mode'];
        $cacheDir = sys_get_temp_dir().'/thelia-sql-mode-verdict-'.bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($cacheDir);

        try {
            $con->query("SET SESSION sql_mode='".implode(',', $divergentSessionModes)."'");

            // Prove the sabotage took before asserting anything about the
            // verdict: the session really diverges from the server
            // configuration, in a verdict-flipping way.
            $sessionModes = $this->parseModes(
                (string) $con->query('SELECT @@SESSION.sql_mode')->fetchColumn(),
            );
            self::assertSame($divergentSessionModes, $sessionModes, 'sanity: SET SESSION sql_mode was not applied');
            self::assertNotSame($globalModes, $sessionModes, 'sanity: the session must diverge from the server configuration');
            self::assertNotSame(
                $serverNeedsCorrection,
                $this->applyTheliaCorrections($sessionModes, $isMariaDb) !== $sessionModes,
                'sanity: session state and server configuration must call for opposite verdicts',
            );

            $this->runSqlModeCheckWithFreshCache($con, $cacheDir);

            $cache = require $cacheDir.\DIRECTORY_SEPARATOR.'check_mysql_configurations.php';

            self::assertSame(
                $serverNeedsCorrection,
                (bool) $cache['canUpdate'],
                'The cached verdict must reflect the server-configured sql_mode, not the state of the current session',
            );

            if ($serverNeedsCorrection) {
                self::assertSame($correctedModes, $cache['modes']);
            }
        } finally {
            $con->query("SET SESSION sql_mode='".$originalSessionMode."'");
            (new Filesystem())->remove($cacheDir);
        }
    }

    /**
     * @return string[]
     */
    private function parseModes(string $sqlMode): array
    {
        return array_values(array_filter(explode(',', $sqlMode)));
    }

    /**
     * The corrections Thelia applies, mirroring checkMySQLConfigurations():
     * ensure NO_ENGINE_SUBSTITUTION, and on MySQL drop ONLY_FULL_GROUP_BY.
     *
     * @param string[] $modes
     *
     * @return string[]
     */
    private function applyTheliaCorrections(array $modes, bool $isMariaDb): array
    {
        if (!\in_array('NO_ENGINE_SUBSTITUTION', $modes, true)) {
            $modes[] = 'NO_ENGINE_SUBSTITUTION';
        }

        if (!$isMariaDb) {
            $modes = array_diff($modes, ['ONLY_FULL_GROUP_BY']);
        }

        return array_values($modes);
    }

    private function runSqlModeCheckWithFreshCache(ConnectionInterface $con, string $cacheDir): void
    {
        $kernel = new class($cacheDir) extends TheliaKernel {
            public function __construct(private readonly string $sqlModeCacheDir)
            {
            }

            public function getCacheDir(): string
            {
                return $this->sqlModeCacheDir;
            }

            public function runSqlModeCheck(ConnectionInterface $con): void
            {
                $this->checkMySQLConfigurations($con);
            }
        };

        $kernel->runSqlModeCheck($con);
    }
}
