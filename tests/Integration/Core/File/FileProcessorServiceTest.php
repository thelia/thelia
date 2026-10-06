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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\File\Exception\ProcessFileException;
use Thelia\Core\File\FileConfiguration;
use Thelia\Core\File\FileManager;
use Thelia\Core\File\Service\FileProcessorService;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Trait\CreatesTestFiles;

/**
 * A caller that passes no constraint must still get the shop upload policy:
 * that is what the Twig back office does, and forgetting it let an administrator
 * upload any file type.
 */
final class FileProcessorServiceTest extends IntegrationTestCase
{
    use CreatesTestFiles;

    protected function tearDown(): void
    {
        $this->cleanUpTestFiles();
        // The database changes are rolled back, the static config cache is not.
        ConfigQuery::resetCache();
        parent::tearDown();
    }

    public function testImageUploadWithoutExplicitConstraintsRefusesANonImage(): void
    {
        $this->expectException(ProcessFileException::class);

        $this->process('payload.exe', 'not an image', 'image');
    }

    public function testDocumentUploadWithoutExplicitConstraintsRefusesABlacklistedExtension(): void
    {
        $this->expectException(ProcessFileException::class);

        $this->process('payload.exe', 'MZ', 'document');
    }

    public function testLegitimateUploadsAreStillAccepted(): void
    {
        $this->validate('kitten.png', $this->pngBytes(), 'image');
        $this->validate('invoice.pdf', '%PDF-1.4', 'document');

        $this->expectNotToPerformAssertions();
    }

    public function testAllowedImageMimeTypesCanBeNarrowedByConfiguration(): void
    {
        ConfigQuery::write(FileConfiguration::IMAGE_MIME_TYPES_VARIABLE, 'image/png');

        $this->validate('kitten.png', $this->pngBytes(), 'image');

        $this->expectException(ProcessFileException::class);

        $this->validate('kitten.gif', 'GIF89a'.str_repeat("\x00", 16), 'image');
    }

    public function testAllowedImageMimeTypesCanBeExtendedByConfiguration(): void
    {
        ConfigQuery::write(FileConfiguration::IMAGE_MIME_TYPES_VARIABLE, 'image/png, text/plain');

        $this->validate('notes.txt', 'plain text', 'image');

        $this->expectNotToPerformAssertions();
    }

    public function testConfigurationCannotReEnableAServerExecutableExtension(): void
    {
        ConfigQuery::write(FileConfiguration::DOCUMENT_EXTENSION_BLACKLIST_VARIABLE, 'nothing');

        $this->expectException(ProcessFileException::class);

        $this->validate('shell.php', '<?php echo 1;', 'document');
    }

    /**
     * Storage keeps only [a-zA-Z0-9-_.] of the client name (FileManager::renameFile()):
     * each of these names passes a check made on the name as sent, and is stored
     * under a server-executable one.
     *
     * @return iterable<string, array{string}>
     */
    public static function nameStoredAsServerExecutableProvider(): iterable
    {
        yield 'trailing space' => ['report.php '];
        yield 'trailing tab' => ["report.php\t"];
        yield 'space inside the extension' => ['report.ph p'];
        yield 'character outside the stored alphabet' => ['hash.ph#p '];
        yield 'zero-width space' => ["report.p\u{200B}hp"];
        yield 'upper case and trailing space' => ['report.PHP '];
        yield 'another executable extension' => ['report.phtml '];
        yield 'executable segment before an empty extension' => ['report.ph p.'];
    }

    #[DataProvider('nameStoredAsServerExecutableProvider')]
    public function testADocumentStoredUnderAServerExecutableNameIsRefused(string $fileName): void
    {
        $storedName = $this->getService(FileManager::class)->renameFile(1, $this->upload($fileName, '<?php echo 1;'));
        self::assertNotNull(FileConfiguration::findExecutableExtension($storedName), \sprintf('"%s" is stored as "%s"', $fileName, $storedName));

        $this->expectException(ProcessFileException::class);

        $this->validate($fileName, '<?php echo 1;', 'document');
    }

    public function testADocumentStoredUnderABlacklistedExtensionIsRefused(): void
    {
        $this->expectException(ProcessFileException::class);

        $this->validate('payload.exe ', 'MZ', 'document');
    }

    public function testADocumentNameCleanedUpByStorageIsStillAccepted(): void
    {
        $this->validate('invoice.pdf ', '%PDF-1.4', 'document');
        $this->validate('Mon devis (1).pdf', '%PDF-1.4', 'document');

        $this->expectNotToPerformAssertions();
    }

    public function testSanitizeUploadReadsTheNameTheSvgIsStoredUnder(): void
    {
        // The leading comment makes the content sniff as text/html, and the trailing space
        // hides the extension from the name as sent: the file is still stored as an .svg.
        $upload = $this->upload('drawing.svg ', '<!-- drawing --><svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>');

        $this->getService(FileProcessorService::class)->sanitizeUpload($upload);

        self::assertStringNotContainsString('alert', (string) file_get_contents($upload->getPathname()));
    }

    /**
     * A document is served from the shop origin: a browser opening one of these runs
     * what it holds there.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function browserActiveDocumentProvider(): iterable
    {
        $script = '<html><script>alert(1)</script></html>';
        $xhtmlScript = '<?xml version="1.0"?><x:script xmlns:x="http://www.w3.org/1999/xhtml">alert(1)</x:script>';

        yield 'html page' => ['evil.html', $script];
        yield 'upper-case htm page' => ['evil.HTM', $script];
        yield 'xhtml page' => ['evil.xhtml', $xhtmlScript];
        yield 'xht page' => ['evil.xht', $xhtmlScript];
        yield 'mhtml archive' => ['evil.mhtml', "MIME-Version: 1.0\r\n\r\n".$script];
        yield 'xml document' => ['evil.xml', $xhtmlScript];
        yield 'xsl stylesheet' => ['evil.xsl', $xhtmlScript];
        yield 'rdf document' => ['evil.rdf', $xhtmlScript];
        yield 'compressed svg' => ['evil.svgz', (string) gzencode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')];
        yield 'javascript' => ['evil.js', 'alert(1)'];
        yield 'javascript module' => ['evil.mjs', 'alert(1)'];
    }

    #[DataProvider('browserActiveDocumentProvider')]
    public function testADocumentABrowserRunsIsRefused(string $fileName, string $content): void
    {
        $this->expectException(ProcessFileException::class);

        $this->validate($fileName, $content, 'document');
    }

    public function testConfigurationCannotReEnableADocumentABrowserRuns(): void
    {
        ConfigQuery::write(FileConfiguration::DOCUMENT_EXTENSION_BLACKLIST_VARIABLE, 'exe');

        $this->expectException(ProcessFileException::class);

        $this->validate('evil.html', '<html><script>alert(1)</script></html>', 'document');
    }

    public function testAnExplicitDocumentPolicyCannotReEnableADocumentABrowserRuns(): void
    {
        $this->expectException(ProcessFileException::class);

        $this->getService(FileProcessorService::class)->validateUpload(
            $this->upload('evil.html', '<html><script>alert(1)</script></html>'),
            'document',
            [],
            [],
        );
    }

    public function testAnSvgDocumentIsStillAcceptedSinceItIsSanitized(): void
    {
        $this->validate('drawing.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>', 'document');
        $this->validate('notes.txt', 'plain text', 'document');

        $this->expectNotToPerformAssertions();
    }

    public function testExplicitConstraintsTakePrecedenceOverTheShopPolicy(): void
    {
        // The Smarty back office passes its own policy; it must not be overridden.
        $this->getService(FileProcessorService::class)->validateUpload(
            $this->upload('notes.txt', 'plain text'),
            'image',
            ['text/plain' => ['txt']],
            [],
        );

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function activeSvgProvider(): iterable
    {
        yield 'onload handler' => [
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="1" height="1"/></svg>',
            'onload',
        ];
        yield 'script element' => [
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>',
            'alert',
        ];
        yield 'xlink href under another prefix' => [
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:x="http://www.w3.org/1999/xlink"><a x:href="javascript:alert(1)"><rect width="1" height="1"/></a></svg>',
            'javascript',
        ];
        yield 'xml-stylesheet processing instruction' => [
            '<?xml version="1.0"?><?xml-stylesheet type="text/xsl" href="https://example.com/x.xsl"?><svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>',
            'xml-stylesheet',
        ];
        yield 'entity declared in the document type' => [
            '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY payload "&#60;script&#62;alert(1)&#60;/script&#62;">]><svg xmlns="http://www.w3.org/2000/svg"><text>&payload;</text></svg>',
            'payload',
        ];
        yield 'entity referenced in an attribute value' => [
            '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY link "javascript:alert(1)">]><svg xmlns="http://www.w3.org/2000/svg"><text class="a&link;b">x</text></svg>',
            '&link;',
        ];
        yield 'xhtml element' => [
            '<svg xmlns="http://www.w3.org/2000/svg"><h:iframe xmlns:h="http://www.w3.org/1999/xhtml" src="https://example.com"/><rect width="1" height="1"/></svg>',
            'iframe',
        ];
        yield 'svg document embedded as a data uri' => [
            '<svg xmlns="http://www.w3.org/2000/svg"><use href="data:image/svg+xml;base64,PHN2Zy8+"/></svg>',
            'data:',
        ];
        yield 'javascript uri hidden among animation values' => [
            '<svg xmlns="http://www.w3.org/2000/svg"><a><animate attributeName="href" values="#;javascript:alert(1)"/><rect width="1" height="1"/></a></svg>',
            'javascript',
        ];
    }

    #[DataProvider('activeSvgProvider')]
    public function testSanitizeUploadStripsTheActiveContentOfAnSvg(string $svg, string $forbidden): void
    {
        $upload = $this->upload('logo.svg', $svg);

        $this->getService(FileProcessorService::class)->sanitizeUpload($upload);

        $sanitized = (string) file_get_contents($upload->getPathname());
        self::assertStringNotContainsStringIgnoringCase($forbidden, $sanitized);
        self::assertStringContainsString('<svg', $sanitized, 'The drawing itself is kept.');
    }

    public function testSanitizeUploadKeepsAPlainSvgDrawing(): void
    {
        $upload = $this->upload('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" fill="red"/></svg>');

        $this->getService(FileProcessorService::class)->sanitizeUpload($upload);

        self::assertStringContainsString('<rect width="10" height="10" fill="red"/>', (string) file_get_contents($upload->getPathname()));
    }

    public function testSanitizeUploadKeepsARasterImageEmbeddedInAnSvg(): void
    {
        $embedded = 'data:image/png;base64,'.base64_encode($this->pngBytes());
        $upload = $this->upload('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><image xlink:href="'.$embedded.'" width="1" height="1"/></svg>');

        $this->getService(FileProcessorService::class)->sanitizeUpload($upload);

        self::assertStringContainsString($embedded, (string) file_get_contents($upload->getPathname()));
    }

    public function testSanitizeUploadRefusesAnSvgThatIsNotAnSvgDocument(): void
    {
        $this->expectException(ProcessFileException::class);

        $this->getService(FileProcessorService::class)->sanitizeUpload(
            $this->upload('logo.svg', '<html xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></html>'),
        );
    }

    /**
     * The call the Twig back office makes: no constraint argument at all. The upload
     * has to be refused before the processor reaches the persistence layer.
     */
    private function process(string $fileName, string $content, string $objectType): void
    {
        $this->getService(FileProcessorService::class)->processFile(
            $this->getService(EventDispatcherInterface::class),
            $this->upload($fileName, $content),
            1,
            'product',
            $objectType,
        );
    }

    private function validate(string $fileName, string $content, string $objectType): void
    {
        $this->getService(FileProcessorService::class)->validateUpload(
            $this->upload($fileName, $content),
            $objectType,
        );
    }

    private function upload(string $fileName, string $content): UploadedFile
    {
        $path = $this->createTestTextFile($content);
        $this->trackFileForCleanup($path);

        return $this->createUploadedFile($path, $fileName, 'application/octet-stream');
    }

    private function pngBytes(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
    }
}
