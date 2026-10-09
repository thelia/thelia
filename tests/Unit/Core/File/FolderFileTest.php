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

namespace Thelia\Tests\Unit\Core\File;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\File\FolderFile;

final class FolderFileTest extends TestCase
{
    private string $root;
    private string $folder;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/folder-file-'.uniqid('', true);
        $this->folder = $this->root.'/folder';
        (new Filesystem())->mkdir($this->folder);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    public function testAFileOfTheFolderIsResolvedAndRemovable(): void
    {
        file_put_contents($this->folder.'/a.csv', 'a');

        self::assertSame(realpath($this->folder.'/a.csv'), FolderFile::resolve($this->folder, $this->folder.'/a.csv'));
        self::assertTrue(FolderFile::isRemovable($this->folder, $this->folder.'/a.csv'));
    }

    /**
     * A link of the folder to another file of it goes alone: the other file stays.
     */
    public function testALinkToAnotherFileOfTheFolderIsRemovedAsTheLink(): void
    {
        file_put_contents($this->folder.'/other.csv', 'other');
        symlink($this->folder.'/other.csv', $this->folder.'/link.csv');

        self::assertTrue(FolderFile::isRemovable($this->folder, $this->folder.'/link.csv'));
    }

    public function testALinkOutsideTheFolderIsNeverRemovable(): void
    {
        file_put_contents($this->folder.'/a.csv', 'a');
        symlink($this->folder.'/a.csv', $this->root.'/outside-link.csv');

        self::assertFalse(FolderFile::isRemovable($this->folder, $this->root.'/outside-link.csv'));
    }

    public function testALinkOfTheFolderToAFileOutsideItIsNeither(): void
    {
        file_put_contents($this->root.'/outside.csv', 'outside');
        symlink($this->root.'/outside.csv', $this->folder.'/link.csv');

        self::assertNull(FolderFile::resolve($this->folder, $this->folder.'/link.csv'));
        self::assertFalse(FolderFile::isRemovable($this->folder, $this->folder.'/link.csv'));
    }

    public function testAFolderOfTheFolderIsNeither(): void
    {
        (new Filesystem())->mkdir($this->folder.'/sub');

        self::assertNull(FolderFile::resolve($this->folder, $this->folder.'/sub'));
        self::assertFalse(FolderFile::isRemovable($this->folder, $this->folder.'/sub'));
    }

    public function testARemovedLinkLeavesTheFileItPointedTo(): void
    {
        file_put_contents($this->folder.'/other.csv', 'other');
        symlink($this->folder.'/other.csv', $this->folder.'/link.csv');

        self::assertTrue(FolderFile::remove($this->folder, $this->folder.'/link.csv'));
        self::assertFalse(is_link($this->folder.'/link.csv'));
        self::assertFileExists($this->folder.'/other.csv');
    }

    public function testAPathOutsideTheFolderIsNeverRemoved(): void
    {
        file_put_contents($this->root.'/outside.csv', 'outside');

        self::assertFalse(FolderFile::remove($this->folder, $this->root.'/outside.csv'));
        self::assertFileExists($this->root.'/outside.csv');
    }

    /**
     * A relative path and a path whose folder is gone are read as the file system reads
     * them: from the working directory, and as nothing.
     */
    public function testARelativePathAndAPathWhoseFolderIsGone(): void
    {
        file_put_contents($this->folder.'/a.csv', 'a');
        $workingDirectory = getcwd();
        chdir($this->root);

        try {
            self::assertTrue(FolderFile::isRemovable($this->folder, 'folder/a.csv'));
        } finally {
            chdir((string) $workingDirectory);
        }

        self::assertFalse(FolderFile::isRemovable($this->folder, $this->folder.'/gone/a.csv'));
    }
}
