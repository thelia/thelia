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

namespace Thelia\Tests\Unit\Domain\Catalog\Stock;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Catalog\Stock\CallerTransaction;

/**
 * In production a caller holds a transaction open whenever the connection is in one: nothing is kept
 * from one call to the next, and nothing can be told to look away.
 */
final class CallerTransactionTest extends TestCase
{
    public function testAConnectionInATransactionIsATransactionOfTheCaller(): void
    {
        $connection = $this->createStub(ConnectionInterface::class);
        $connection->method('inTransaction')->willReturn(true);

        self::assertTrue((new CallerTransaction())->isOpen($connection));
    }

    public function testAConnectionOutOfAnyTransactionIsNone(): void
    {
        $connection = $this->createStub(ConnectionInterface::class);
        $connection->method('inTransaction')->willReturn(false);

        self::assertFalse((new CallerTransaction())->isOpen($connection));
    }

    public function testItKeepsNoState(): void
    {
        $class = new \ReflectionClass(CallerTransaction::class);

        self::assertTrue($class->isFinal());
        self::assertTrue($class->isReadOnly());
        self::assertSame([], $class->getProperties(), 'Nothing a test could leave behind for the next request.');
    }
}
