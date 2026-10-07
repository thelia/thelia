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

namespace Thelia\Tests\Integration\Domain\DataTransfer;

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Domain\DataTransfer\ArchiveInspector;
use Thelia\Domain\DataTransfer\Exception\UploadRefusedException;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Test\IntegrationTestCase;

/**
 * An archive is looked into before anything is extracted from it. Played with the
 * kernel for the translator its messages go through.
 */
final class ArchiveInspectorTest extends IntegrationTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/archive-inspector-'.uniqid('', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);

        parent::tearDown();
    }

    public function testAnOrdinaryArchiveIsAccepted(): void
    {
        (new ArchiveInspector())->assertExtractable($this->zip(['stock.csv' => "id,stock\n1,2\n"]), 'zip');

        $this->addToAssertionCount(1);
    }

    public function testAnArchiveWithTooManyFilesIsRefused(): void
    {
        $this->expectException(FormValidationException::class);

        (new ArchiveInspector(maxEntries: 2))->assertExtractable($this->zip(['a.csv' => 'a', 'b.csv' => 'b', 'c.csv' => 'c']), 'zip');
    }

    /**
     * A few kilobytes that would fill the disk once extracted.
     */
    public function testAnArchiveTooLargeOnceExtractedIsRefused(): void
    {
        $this->expectException(FormValidationException::class);

        (new ArchiveInspector(maxExtractedBytes: 1000))->assertExtractable($this->zip(['stock.csv' => str_repeat('0', 5000)]), 'zip');
    }

    public function testAnArchiveClimbingOutOfItsFolderIsRefused(): void
    {
        $this->expectException(FormValidationException::class);

        (new ArchiveInspector())->assertExtractable($this->zip(['../../public/stock.php' => '<?php']), 'zip');
    }

    /**
     * An upload waits under a name without extension, which PharData cannot read on
     * its own.
     */
    public function testATarArchiveIsReadWhateverItsName(): void
    {
        $tar = new \PharData($this->directory.'/stock.tar');
        $tar->addFromString('stock.csv', str_repeat('0', 5000));
        unset($tar);
        rename($this->directory.'/stock.tar', $this->directory.'/phpUpload');

        $this->expectException(FormValidationException::class);
        $this->expectExceptionMessage('once extracted');

        (new ArchiveInspector(maxExtractedBytes: 1000))->assertExtractable($this->directory.'/phpUpload', 'tar');
    }

    public function testALinkInAZipIsRefused(): void
    {
        $path = $this->zip(['stock.csv' => '/etc/passwd']);
        $zip = new \ZipArchive();
        $zip->open($path);
        $zip->setExternalAttributesName('stock.csv', \ZipArchive::OPSYS_UNIX, 0o120777 << 16);
        $zip->close();

        $this->expectException(UploadRefusedException::class);

        (new ArchiveInspector())->assertExtractable($path, 'zip');
    }

    public function testAnOrdinaryTarIsAccepted(): void
    {
        (new ArchiveInspector())->assertExtractable($this->tar(['stock.csv' => "id,stock\n1,2\n"]), 'tar');

        $this->addToAssertionCount(1);
    }

    public function testATarClimbingOutOfItsFolderIsRefused(): void
    {
        $this->expectException(UploadRefusedException::class);

        (new ArchiveInspector())->assertExtractable($this->tar(['../../public/stock.php' => '<?php']), 'tar');
    }

    public function testALinkInATarIsRefused(): void
    {
        $this->expectException(UploadRefusedException::class);

        (new ArchiveInspector())->assertExtractable($this->tar(['stock.csv' => ''], type: '2'), 'tar');
    }

    public function testATarWithTooManyFilesIsRefused(): void
    {
        $this->expectException(UploadRefusedException::class);

        (new ArchiveInspector(maxEntries: 2))->assertExtractable($this->tar(['a.csv' => 'a', 'b.csv' => 'b', 'c.csv' => 'c']), 'tar');
    }

    /**
     * Read through its compression, block after block: a gzip bomb is refused at its
     * first header, never held in memory.
     */
    public function testACompressedTarIsReadThroughItsCompression(): void
    {
        $path = $this->directory.'/upload';
        file_put_contents($path, (string) gzencode((string) file_get_contents($this->tar(['stock.csv' => str_repeat('0', 5000)]))));

        $this->expectException(UploadRefusedException::class);
        $this->expectExceptionMessage('once extracted');

        (new ArchiveInspector(maxExtractedBytes: 1000))->assertExtractable($path, 'tgz');
    }

    public function testABzip2TarIsReadThroughItsCompression(): void
    {
        if (!\function_exists('bzcompress')) {
            self::markTestSkipped('bz2 is not installed.');
        }

        $path = $this->directory.'/upload';
        file_put_contents($path, (string) bzcompress((string) file_get_contents($this->tar(['stock.csv' => str_repeat('0', 5000)]))));

        $this->expectException(UploadRefusedException::class);

        (new ArchiveInspector(maxExtractedBytes: 1000))->assertExtractable($path, 'bz2');
    }

    /**
     * A long-name record is read through like any entry: it counts against the limits.
     */
    public function testTheRecordsNamingAnEntryCountAgainstTheLimits(): void
    {
        $long = (string) file_get_contents($this->tar(['././@LongLink' => str_repeat('a', 300)."\0"], type: 'L'));
        $file = (string) file_get_contents($this->tar(['stock.csv' => 'a']));
        $path = $this->directory.'/long.tar';
        file_put_contents($path, substr($long, 0, -1024).$file);

        $this->expectException(UploadRefusedException::class);

        (new ArchiveInspector(maxEntries: 1))->assertExtractable($path, 'tar');
    }

    public function testAnEmptyTarIsAccepted(): void
    {
        (new ArchiveInspector())->assertExtractable($this->tar([]), 'tar');

        $this->addToAssertionCount(1);
    }

    /**
     * What an archive declares is written by whoever made it: what was really
     * written is measured too.
     */
    public function testWhatWasReallyExtractedIsMeasured(): void
    {
        mkdir($this->directory.'/extracted');
        file_put_contents($this->directory.'/extracted/stock.csv', str_repeat('0', 5000));

        $this->expectException(UploadRefusedException::class);

        (new ArchiveInspector(maxExtractedBytes: 1000))->assertExtractedSize($this->directory.'/extracted');
    }

    /**
     * A tar written block by block, as tar writes it, whatever the names it holds.
     *
     * @param array<string, string> $files
     */
    private function tar(array $files, string $type = '0'): string
    {
        $tar = '';
        foreach ($files as $name => $content) {
            $header = str_pad($name, 100, "\0").str_pad('0000644', 8, "\0").str_pad('0000000', 8, "\0").str_pad('0000000', 8, "\0")
                .str_pad(\sprintf('%011o', \strlen($content)), 12, "\0").str_pad(\sprintf('%011o', time()), 12, "\0").str_repeat(' ', 8)
                .$type.str_repeat("\0", 100).str_pad("ustar\0", 6, "\0").'00'.str_repeat("\0", 247);
            $checksum = array_sum(array_map(ord(...), str_split($header)));
            $header = substr_replace($header, str_pad(\sprintf('%06o', $checksum), 7, "\0")."\0", 148, 8);
            $tar .= $header.str_pad($content, (int) (ceil(\strlen($content) / 512) * 512), "\0");
        }

        $path = $this->directory.'/'.uniqid().'.tar';
        file_put_contents($path, $tar.str_repeat("\0", 1024));

        return $path;
    }

    /**
     * @param array<string, string> $files
     */
    private function zip(array $files): string
    {
        $path = $this->directory.'/'.uniqid().'.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }
}
