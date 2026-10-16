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

namespace Thelia\Tests\Unit\Domain\DataTransfer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Domain\DataTransfer\Service\ExportCachePurger;

final class ExportCachePurgerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/export-cache-purger-'.uniqid('', true);
        mkdir($this->directory);

        touch($this->directory.'/old.csv', strtotime('-3 days'));
        touch($this->directory.'/fresh.csv');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testAFileOlderThanADayIsDeleted(): void
    {
        self::assertSame(1, (new ExportCachePurger())->purgeOldExportFiles($this->directory));

        self::assertFileDoesNotExist($this->directory.'/old.csv');
        self::assertFileExists($this->directory.'/fresh.csv');
    }

    /**
     * maintenance:purge --dry-run promises to write nothing: the count deletes nothing.
     */
    public function testADryRunCountsTheFilesAndDeletesNone(): void
    {
        self::assertSame(1, (new ExportCachePurger())->countOldExportFiles($this->directory));

        self::assertFileExists($this->directory.'/old.csv');
        self::assertFileExists($this->directory.'/fresh.csv');
    }

    public function testAMissingDirectoryHoldsNothing(): void
    {
        self::assertSame(0, (new ExportCachePurger())->countOldExportFiles($this->directory.'/missing'));
        self::assertSame(0, (new ExportCachePurger())->purgeOldExportFiles($this->directory.'/missing'));
    }

    /**
     * A link in the export folder goes; the file it points to, wherever it is, never does.
     */
    public function testAnOldLinkGoesAndItsTargetStays(): void
    {
        $target = sys_get_temp_dir().'/export-cache-purger-target-'.uniqid('', true);
        touch($target, strtotime('-3 days'));
        symlink($target, $this->directory.'/link.csv');

        try {
            (new ExportCachePurger())->purgeOldExportFiles($this->directory);

            self::assertFileExists($target);
            self::assertFalse(is_link($this->directory.'/link.csv'));
        } finally {
            (new Filesystem())->remove($target);
        }
    }
}
