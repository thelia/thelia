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

namespace Thelia\Tests\Support\Stock;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Thelia\Domain\Catalog\Stock\CallerTransactionInterface;

/**
 * The test environment's view of the transaction of a caller (registered in place of
 * CallerTransaction for the test environment only: App\Kernel imports tests/Support/config/services.php).
 *
 * A test that wraps itself in a transaction, to roll its fixtures back, is no caller: it says
 * so here, and only the levels opened after it count. A test that says nothing sees the
 * production answer.
 */
final class TestCallerTransaction implements CallerTransactionInterface
{
    private int $levelsOfTheTest = 0;

    public function __construct(private readonly CallerTransactionInterface $inner)
    {
    }

    public function isOpen(ConnectionInterface $connection): bool
    {
        if (!$this->inner->isOpen($connection)) {
            return false;
        }

        return !$connection instanceof ConnectionWrapper || $connection->getNestedTransactionCount() > $this->levelsOfTheTest;
    }

    /** The levels open on the connection now are the test's own, not a caller's. */
    public function ignoreTheLevelsOpenOn(ConnectionInterface $connection): void
    {
        $this->levelsOfTheTest = $connection instanceof ConnectionWrapper ? $connection->getNestedTransactionCount() : 0;
    }

    public function reset(): void
    {
        $this->levelsOfTheTest = 0;
    }
}
