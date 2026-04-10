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

namespace Thelia\Test;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Thelia\Core\TheliaKernel;
use Thelia\Core\Translation\Translator;

/**
 * Base class for Thelia integration tests.
 *
 * Boots the kernel, initializes Translator, and wraps each test in a
 * transaction rolled back in tearDown() for full isolation.
 *
 * Prerequisites (run before the test suite):
 *   bin/console thelia:database:create --env=test
 *   bin/console thelia:database:populate --env=test
 *   bin/console cache:warmup --env=test
 *
 * Constraints:
 *   - Auto-increment values are NOT rolled back: never hardcode IDs.
 *   - The kernel is static across tests: do not mutate the container.
 *   - DDL/TRUNCATE tests must set $useTransaction = false and clean up manually.
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    protected bool $useTransaction = true;

    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        self::bootKernel();

        if (!TheliaKernel::isInstalled()) {
            $this->markTestSkipped(
                'Test database not available. Run: '
                .'bin/console thelia:database:create --env=test '
                .'&& bin/console thelia:database:populate --env=test',
            );
        }

        try {
            Translator::getInstance();
        } catch (\RuntimeException) {
            static::getContainer()->get('thelia.translator');
        }

        if ($this->useTransaction) {
            $this->connection = Propel::getConnection('TheliaMain');
            $this->connection->beginTransaction();
        }
    }

    protected function tearDown(): void
    {
        if ($this->useTransaction && $this->connection instanceof ConnectionInterface && $this->connection->inTransaction()) {
            $this->connection->rollBack();
        }

        $this->connection = null;

        parent::tearDown();
    }

    protected function getService(string $id): object
    {
        return static::getContainer()->get($id);
    }

    protected function getPropelConnection(): ConnectionInterface
    {
        return Propel::getConnection('TheliaMain');
    }
}
