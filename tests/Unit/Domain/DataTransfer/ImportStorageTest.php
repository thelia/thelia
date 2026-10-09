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
use Thelia\Domain\DataTransfer\Job\ImportStorage;
use Thelia\Model\ImportJob;

final class ImportStorageTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir().'/import-storage-'.uniqid('', true);
        (new Filesystem())->mkdir($this->project.'/'.ImportStorage::DIRECTORY);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->project);
    }

    /**
     * Only a file goes: a row naming a folder of the storage never gets that folder
     * removed with all it holds.
     */
    public function testARowNamingAFolderOfTheStorageLeavesItAlone(): void
    {
        $folder = $this->project.'/'.ImportStorage::DIRECTORY.'/2026-10-09';
        (new Filesystem())->dumpFile($folder.'/waiting.csv', 'ref;stock');

        (new ImportStorage($this->project))->discardFileOf((new ImportJob())->setFilePath(ImportStorage::DIRECTORY.'/2026-10-09'));

        self::assertFileExists($folder.'/waiting.csv');
    }

    /**
     * A link of the storage to a file elsewhere never gets that file removed.
     */
    public function testALinkOfTheStorageToAFileElsewhereLeavesThatFileAlone(): void
    {
        $target = $this->project.'/elsewhere.csv';
        file_put_contents($target, 'ref;stock');
        $link = $this->project.'/'.ImportStorage::DIRECTORY.'/link.csv';
        symlink($target, $link);

        (new ImportStorage($this->project))->discardFileOf((new ImportJob())->setFilePath(ImportStorage::DIRECTORY.'/link.csv'));

        self::assertFileExists($target);
    }

    public function testTheFileOfAJobGoes(): void
    {
        $file = $this->project.'/'.ImportStorage::DIRECTORY.'/stock.csv';
        file_put_contents($file, 'ref;stock');

        (new ImportStorage($this->project))->discardFileOf((new ImportJob())->setFilePath(ImportStorage::DIRECTORY.'/stock.csv'));

        self::assertFileDoesNotExist($file);
    }
}
