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
namespace Thelia\Tests\Unit\Core\Archiver;

use PHPUnit\Framework\TestCase;
use Thelia\Core\Archiver\Archiver\ZipArchiver;

final class ZipArchiverTest extends TestCase
{
    /**
     * An export that fails before its archive is created still closes the archiver.
     */
    public function testAnArchiverNeverOpenedClosesAtOnce(): void
    {
        self::assertTrue((new ZipArchiver())->close());
    }

    /**
     * An export that fails while it fills its archive: what was added is dropped, and
     * no file is written only to be removed.
     */
    public function testADiscardedArchiveWritesNoFile(): void
    {
        $base = sys_get_temp_dir().'/thelia_zip_archiver_'.uniqid();
        $added = $base.'.csv';
        file_put_contents($added, 'id');

        try {
            $archiver = (new ZipArchiver())->create($base);
            $archiver->add($added);
            $archiver->discard();

            self::assertFileDoesNotExist($archiver->getArchivePath());
        } finally {
            @unlink($added);
            @unlink($base.'.zip');
        }
    }
}
