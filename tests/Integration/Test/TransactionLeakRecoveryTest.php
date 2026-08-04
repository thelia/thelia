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

namespace Thelia\Tests\Integration\Test;

use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Thelia\Test\IntegrationTestCase;

/**
 * The two tests below are order-dependent on purpose: the first one leaves a
 * nested Propel transaction level open, exactly like a test failing while
 * application code is between beginTransaction() and commit(). tearDown()
 * must roll the whole transaction back — a leaked one keeps its metadata
 * locks for the rest of the PHPUnit process, and the next DDL statement then
 * waits on the schema lock until lock_wait_timeout (the 6-hour CI hang).
 */
final class TransactionLeakRecoveryTest extends IntegrationTestCase
{
    public function testLeavesANestedTransactionLevelOpenLikeAFailingTest(): void
    {
        $connection = Propel::getConnection('TheliaMain');
        $connection->beginTransaction();

        self::assertTrue($connection->inTransaction());
        // No commit/rollBack: tearDown() runs with a nesting count above 1.
    }

    public function testTearDownRolledBackTheWholeLeakedTransaction(): void
    {
        $connection = Propel::getConnection('TheliaMain');

        self::assertInstanceOf(ConnectionWrapper::class, $connection);
        self::assertSame(
            1,
            $connection->getNestedTransactionCount(),
            'Only the per-test wrapper transaction must be open: the level leaked by the previous test was not rolled back.',
        );
    }
}
