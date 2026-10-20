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

namespace Thelia\Tests\Integration\Core\File;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\File\Exception\FileException;
use Thelia\Core\File\FileManager;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * Storage reads what a file holds, not only the name the client sent: every module that
 * stores an upload through FileManager gets the same rule.
 */
final class FileManagerContentTest extends IntegrationTestCase
{
    use CreatesTestFiles;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        parent::tearDown();
    }

    public function testAPhpScriptNamedAsAnImageIsNotStored(): void
    {
        $path = $this->createTestTextFile("<?php\nsystem(\$_GET['cmd']);\n");
        $this->trackFileForCleanup($path);

        $this->expectException(FileException::class);

        (new FileManager([]))->assertStorable(new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function phpCodeProvider(): iterable
    {
        $script = "system(\$_GET['cmd']);";

        yield 'opening tag after blanks' => ["  <?php {$script} ?>", 'photo.jpg'];
        yield 'short echo tag' => ["<?= {$script} ?>", 'photo.jpg'];
        yield 'opening tag inside HTML' => ["<html><body><?php {$script} ?></body></html>", 'photo.jpg'];
        yield 'opening tag in upper case' => ["<html><?PHP\n{$script}", 'notes.txt'];
        yield 'opening tag closing the file' => ['<html><?php', 'notes.txt'];
    }

    #[DataProvider('phpCodeProvider')]
    public function testAFileHoldingPhpCodeIsNotStored(string $content, string $fileName): void
    {
        $path = $this->createTestTextFile($content);
        $this->trackFileForCleanup($path);

        $this->expectException(FileException::class);

        (new FileManager([]))->assertStorable(new UploadedFile($path, $fileName, 'image/jpeg', null, true));
    }

    public function testAnImageCarryingPhpCodeIsNotStored(): void
    {
        $path = $this->createTestPng();
        $this->trackFileForCleanup($path);
        file_put_contents($path, "<?php system(\$_GET['cmd']); ?>", \FILE_APPEND);

        $this->expectException(FileException::class);

        (new FileManager([]))->assertStorable(new UploadedFile($path, 'photo.png', 'image/png', null, true));
    }

    public function testAnOpeningTagSplitBetweenTwoReadBlocksIsFound(): void
    {
        $path = $this->createTestPng();
        $this->trackFileForCleanup($path);
        // Read blocks are 64 KiB: the tag straddles the end of the first one.
        file_put_contents($path, str_repeat("\0", 65536 - (int) filesize($path) - 3).'<?php system(1);', \FILE_APPEND);

        $this->expectException(FileException::class);

        (new FileManager([]))->assertStorable(new UploadedFile($path, 'photo.png', 'image/png', null, true));
    }

    public function testAPdfIsStored(): void
    {
        $path = $this->createTestTextFile("%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
        $this->trackFileForCleanup($path);
        $uploadedFile = new UploadedFile($path, 'brochure.pdf', 'application/pdf', null, true);
        $fileManager = new FileManager([]);

        $fileManager->assertStorable($uploadedFile);

        self::assertSame('brochure-3.pdf', $fileManager->renameFile(3, $uploadedFile));
    }

    public function testAnImageWhoseExtensionMatchesItsContentKeepsItsName(): void
    {
        $path = $this->createTestPng();
        $this->trackFileForCleanup($path);
        $uploadedFile = new UploadedFile($path, 'Photo.png', 'image/png', null, true);
        $fileManager = new FileManager([]);

        $fileManager->assertStorable($uploadedFile);

        self::assertSame('photo-3.png', $fileManager->renameFile(3, $uploadedFile));
    }

    public function testAnImageWhoseExtensionContradictsItsContentTakesTheExtensionOfItsContent(): void
    {
        $path = $this->createTestPng();
        $this->trackFileForCleanup($path);

        $storedName = (new FileManager([]))->renameFile(3, new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true));

        self::assertSame('photo-3.png', $storedName, 'The base name stays the one the client sent.');
    }

    public function testAFileOfAnotherTypeKeepsTheExtensionItWasSentWith(): void
    {
        $path = $this->createTestTextFile('Opening hours: 9am to 6pm');
        $this->trackFileForCleanup($path);

        self::assertSame(
            'opening-hours-3.pdf',
            (new FileManager([]))->renameFile(3, new UploadedFile($path, 'opening-hours.pdf', 'application/pdf', null, true)),
        );
    }
}
