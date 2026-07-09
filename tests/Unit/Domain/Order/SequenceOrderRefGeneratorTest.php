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

namespace Thelia\Tests\Unit\Domain\Order;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Order\Service\SequenceOrderRefGenerator;

final class SequenceOrderRefGeneratorTest extends TestCase
{
    public function testFormatKeepsTheHistoricalOrdPaddedLayout(): void
    {
        self::assertSame('ORD000000000001', SequenceOrderRefGenerator::format(1));
        self::assertSame('ORD000000001051', SequenceOrderRefGenerator::format(1051));
        self::assertSame('ORD999999999999', SequenceOrderRefGenerator::format(999999999999));
    }

    public function testFormatDoesNotTruncateNumbersLargerThanThePadding(): void
    {
        self::assertSame('ORD1000000000000', SequenceOrderRefGenerator::format(1000000000000));
    }
}
