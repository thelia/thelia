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

namespace Thelia\Tests\Unit\Test\Volume;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Test\Volume\BatchInserter;

final class BatchInserterTest extends TestCase
{
    public function testRowsAreCutInBatchesOfTheGivenSizeTheLastOneShorter(): void
    {
        $inserter = new BatchInserter($this->createStub(ConnectionInterface::class), 3);

        $sizes = array_map('count', iterator_to_array($inserter->batches(array_fill(0, 7, [1])), false));

        self::assertSame([3, 3, 1], $sizes);
    }

    public function testNoRowMakesNoBatch(): void
    {
        $inserter = new BatchInserter($this->createStub(ConnectionInterface::class), 3);

        self::assertSame([], iterator_to_array($inserter->batches([]), false));
    }

    public function testABatchHoldsAtLeastOneRow(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BatchInserter($this->createStub(ConnectionInterface::class), 0);
    }
}
