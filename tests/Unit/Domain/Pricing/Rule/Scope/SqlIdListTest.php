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

namespace Thelia\Tests\Unit\Domain\Pricing\Rule\Scope;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Pricing\Rule\Scope\SqlIdList;

/**
 * The ids a resolver inlines in its SQL: integers only, whatever it was handed, each
 * once, and an empty list that still parses.
 */
final class SqlIdListTest extends TestCase
{
    public function testEachIdComesOnceAsAnInteger(): void
    {
        self::assertSame('(3, 12)', SqlIdList::inList(['3', 12, '03', 3]));
    }

    public function testAnythingButTheIntegerIsLeftOutOfTheStatement(): void
    {
        self::assertSame('(12)', SqlIdList::inList(['12) OR (1 = 1']));
    }

    public function testAnEmptyListMatchesNothing(): void
    {
        self::assertSame('(-1)', SqlIdList::inList([]));
    }
}
