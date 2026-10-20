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
use Thelia\Domain\DataTransfer\Service\ExportCachePurger;

final class PurgeExportCacheListenerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/thelia-export-cache-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
        touch($this->directory.'/old.csv', time() - 3 * 86400);
        touch($this->directory.'/recent.csv');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    }

    public function testThePurgerDeletesOnlyTheFilesOlderThanADay(): void
    {
        $purger = new ExportCachePurger();

        self::assertSame(1, $purger->countOldExportFiles($this->directory));
        self::assertFileExists($this->directory.'/old.csv', 'Counting deletes nothing.');

        self::assertSame(1, $purger->purgeOldExportFiles($this->directory));
        self::assertFileDoesNotExist($this->directory.'/old.csv');
        self::assertFileExists($this->directory.'/recent.csv');
    }

    public function testAMissingDirectoryHoldsNothing(): void
    {
        $purger = new ExportCachePurger();

        self::assertSame(0, $purger->countOldExportFiles($this->directory.'/missing'));
        self::assertSame(0, $purger->purgeOldExportFiles($this->directory.'/missing'));
    }
}
