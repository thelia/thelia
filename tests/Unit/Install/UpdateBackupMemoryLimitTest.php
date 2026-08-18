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
 * `Update::checkBackupIsPossible()` sizes the pre-update backup against
 * `memory_limit`, so its parsing has to follow the php.ini shorthand-byte
 * semantics: a negative value means unlimited, k/m/g suffixes are
 * case-insensitive, a bare number is bytes, and a fractional prefix such
 * as "0.5G" is truncated to its integer part exactly as PHP truncates it.
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

    public function testBackupIsAlwaysPossibleWhenMemoryIsUnlimited(): void
    {
        $update = $this->updateWithDatabaseSize(100000.0);

        self::assertTrue($this->checkBackupWithMemoryLimit($update, '-1'));
    }

    public function testBackupIsPossibleWhenTheDatabaseFitsUnderTheFiniteLimit(): void
    {
        $update = $this->updateWithDatabaseSize(10.0);

        // (512 - 64) / 8 = 56 MB allowed
        self::assertTrue($this->checkBackupWithMemoryLimit($update, '512M'));
    }

    public function testBackupIsRefusedWhenTheDatabaseExceedsTheFiniteLimit(): void
    {
        $update = $this->updateWithDatabaseSize(100.0);

        self::assertFalse($this->checkBackupWithMemoryLimit($update, '512M'));
    }

    public function testBackupIsPossibleWhenTheLimitIsGivenAsBareBytes(): void
    {
        $update = $this->updateWithDatabaseSize(5.0);

        // 134217728 bytes = 128 MB, (128 - 64) / 8 = 8 MB allowed
        self::assertTrue($this->checkBackupWithMemoryLimit($update, '134217728'));
    }

    public function testBackupIsRefusedWhenTheLimitIsTooSmallForTheDump(): void
    {
        $update = $this->updateWithDatabaseSize(5.0);

        // (96 - 64) / 8 = 4 MB allowed, the 5 MB database does not fit
        self::assertFalse($this->checkBackupWithMemoryLimit($update, '96M'));
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
