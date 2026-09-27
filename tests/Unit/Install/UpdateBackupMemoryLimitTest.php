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

namespace Thelia\Tests\Unit\Install;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Core\Install\Update;

/**
 * `Update::parseMemoryLimit()` follows the php.ini shorthand-byte semantics:
 * a negative value means unlimited, k/m/g suffixes are case-insensitive, a
 * bare number is bytes, and a fractional prefix such as "0.5G" is truncated
 * to its integer part exactly as PHP truncates it.
 *
 * `Update::checkBackupIsPossible()` used to size the pre-update backup against
 * `memory_limit` and refused any database larger than an eighth of it. The
 * backup now streams, so no memory_limit may refuse it any more.
 */
final class UpdateBackupMemoryLimitTest extends TestCase
{
    #[DataProvider('memoryLimitProvider')]
    public function testParseMemoryLimitFollowsPhpShorthandByteSemantics(string $iniValue, int $expectedBytes): void
    {
        self::assertSame($expectedBytes, Update::parseMemoryLimit($iniValue));
    }

    public static function memoryLimitProvider(): iterable
    {
        yield 'unlimited' => ['-1', -1];
        yield 'megabytes, uppercase suffix' => ['128M', 134217728];
        yield 'megabytes, lowercase suffix' => ['64m', 67108864];
        yield 'gigabytes' => ['1G', 1073741824];
        yield 'kilobytes' => ['512k', 524288];
        yield 'bare bytes' => ['134217728', 134217728];
        yield 'surrounding whitespace' => [' 256M ', 268435456];
        yield 'fractional prefix truncated to int, as PHP does' => ['0.5G', 0];
        yield 'fractional prefix keeps its integer part' => ['1.9G', 1073741824];
        yield 'any negative value is unlimited' => ['-2G', -2147483648];
        yield 'unknown suffix falls back to bytes' => ['1x', 1];
        yield 'no leading digits' => ['abc', 0];
    }

    #[DataProvider('memoryLimitsAndDatabaseSizes')]
    public function testNoMemoryLimitRefusesTheBackupAnyMore(string $memoryLimit, float $databaseSizeInMegabytes): void
    {
        $update = $this->updateWithDatabaseSize($databaseSizeInMegabytes);

        self::assertTrue($this->checkBackupWithMemoryLimit($update, $memoryLimit));
    }

    public static function memoryLimitsAndDatabaseSizes(): iterable
    {
        yield 'unlimited' => ['-1', 100000.0];
        yield 'a database larger than an eighth of the limit' => ['512M', 100.0];
        yield 'a limit given as bare bytes' => ['134217728', 20.0];
        yield 'a limit barely above the old 64 MB reserve' => ['96M', 5.0];
        yield 'a 2.8 GB database under a 512 MB limit' => ['512M', 2867.0];
    }

    private function checkBackupWithMemoryLimit(Update $update, string $memoryLimit): bool
    {
        $initialLimit = \ini_get('memory_limit');
        ini_set('memory_limit', $memoryLimit);

        try {
            return $update->checkBackupIsPossible();
        } finally {
            ini_set('memory_limit', $initialLimit);
        }
    }

    private function updateWithDatabaseSize(float $sizeInMegabytes): Update
    {
        return new class($sizeInMegabytes) extends Update {
            public function __construct(private readonly float $databaseSize)
            {
            }

            public function getDataBaseSize(): float
            {
                return $this->databaseSize;
            }
        };
    }
}
