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
