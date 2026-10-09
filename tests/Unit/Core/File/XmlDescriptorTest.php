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
use Thelia\Core\File\XmlDescriptor;

/**
 * A descriptor is read and checked closed: a reason for every refusal, no warning of
 * PHP, and nothing passed for want of an answer.
 */
final class XmlDescriptorTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().'/thelia-xml-descriptor-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->workDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->workDir);
    }

    public function testADescriptorIsLoadedOrRefusedWithAReason(): void
    {
        file_put_contents($this->workDir.'/good.xml', '<a><b/></a>');
        file_put_contents($this->workDir.'/bad.xml', '<a><b></a>');

        self::assertSame([], XmlDescriptor::loadingErrors(new \DOMDocument(), $this->workDir.'/good.xml'));
        self::assertSame(['it is not a readable file'], XmlDescriptor::loadingErrors(new \DOMDocument(), $this->workDir.'/gone.xml'));
        self::assertSame(['it is not a readable file'], XmlDescriptor::loadingErrors(new \DOMDocument(), $this->workDir));

        $notXml = XmlDescriptor::loadingErrors(new \DOMDocument(), $this->workDir.'/bad.xml');
        self::assertNotSame([], $notXml);
        self::assertStringContainsString('tag mismatch', $notXml[0]);
        self::assertMatchesRegularExpression('/ \(Code \d+\) on line 1$/', $notXml[0]);
    }

    public function testADescriptorIsCheckedAgainstItsSchemaOrRefusedWithAReason(): void
    {
        file_put_contents($this->workDir.'/a.xsd', '<?xml version="1.0"?><xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"><xs:element name="a"><xs:complexType><xs:sequence><xs:element name="b"/></xs:sequence></xs:complexType></xs:element></xs:schema>');
        file_put_contents($this->workDir.'/garbage.xsd', 'garbage');
        $conforming = new \DOMDocument();
        $conforming->loadXML('<a><b/></a>');
        $other = new \DOMDocument();
        $other->loadXML('<a><c/></a>');

        self::assertSame([], XmlDescriptor::schemaErrors($conforming, $this->workDir.'/a.xsd'));
        self::assertSame(['the schema is missing'], XmlDescriptor::schemaErrors($conforming, ''));

        $notConforming = XmlDescriptor::schemaErrors($other, $this->workDir.'/a.xsd');
        self::assertCount(1, $notConforming);
        self::assertStringContainsString("'c'", $notConforming[0]);

        $notChecked = XmlDescriptor::schemaErrors($conforming, $this->workDir.'/garbage.xsd');
        self::assertGreaterThan(1, \count($notChecked));
        self::assertSame('the descriptor could not be checked against garbage.xsd', $notChecked[0]);
        self::assertStringContainsString('Invalid Schema', end($notChecked));

        // A path PHP refuses before libxml sees it.
        $refused = XmlDescriptor::schemaErrors($conforming, $this->workDir."/a\0.xsd");
        self::assertSame('the descriptor could not be checked against a?.xsd', $refused[0]);
        self::assertStringContainsString('null bytes', $refused[1]);
    }

    /**
     * The error handler and the error mode of libxml are the caller's, given back as they
     * were whatever libxml answered; the error buffer of libxml is emptied, what the
     * caller left in it included.
     */
    public function testTheErrorHandlingOfTheCallerIsGivenBack(): void
    {
        file_put_contents($this->workDir.'/good.xml', '<a><b/></a>');
        file_put_contents($this->workDir.'/bad.xml', '<a><b></a>');
        file_put_contents($this->workDir.'/garbage.xsd', 'garbage');
        $handler = static fn (): bool => false;

        foreach ([true, false] as $mode) {
            set_error_handler($handler);
            $previousMode = libxml_use_internal_errors($mode);

            try {
                libxml_use_internal_errors(true);
                (new \DOMDocument())->loadXML('<a>');
                self::assertCount(1, libxml_get_errors());
                libxml_use_internal_errors($mode);

                XmlDescriptor::loadingErrors(new \DOMDocument(), $this->workDir.'/good.xml');
                XmlDescriptor::loadingErrors(new \DOMDocument(), $this->workDir.'/bad.xml');
                $dom = new \DOMDocument();
                $dom->loadXML('<a/>');
                XmlDescriptor::schemaErrors($dom, $this->workDir.'/garbage.xsd');

                self::assertSame($mode, libxml_use_internal_errors());
                self::assertSame([], libxml_get_errors());
                self::assertSame($handler, set_error_handler(null));
                restore_error_handler();
            } finally {
                libxml_use_internal_errors($previousMode);
                restore_error_handler();
            }
        }
    }

    /**
     * The schemas are tried from the latest version down: a descriptor of an older version
     * is accepted by its schema, and one no schema accepts is refused with what the latest
     * schema said, whatever order the file system lists the schemas in.
     */
    public function testTheLatestVersionIsTriedFirstAndTellsTheErrors(): void
    {
        file_put_contents($this->workDir.'/v1.xsd', '<?xml version="1.0"?><xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"><xs:element name="a"><xs:complexType><xs:sequence><xs:element name="old"/></xs:sequence></xs:complexType></xs:element></xs:schema>');
        file_put_contents($this->workDir.'/v2.xsd', '<?xml version="1.0"?><xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"><xs:element name="a"><xs:complexType><xs:sequence><xs:element name="new"/></xs:sequence></xs:complexType></xs:element></xs:schema>');
        $schemas = [new \SplFileInfo($this->workDir.'/v1.xsd'), new \SplFileInfo($this->workDir.'/v2.xsd')];
        $versions = ['1' => 'v1.xsd', '2' => 'v2.xsd'];
        $check = static fn (\DOMDocument $dom, \SplFileInfo $schema): array => XmlDescriptor::schemaErrors($dom, $schema->getPathname());
        $old = new \DOMDocument();
        $old->loadXML('<a><old/></a>');
        $neither = new \DOMDocument();
        $neither->loadXML('<a><other/></a>');

        self::assertSame(1, XmlDescriptor::matchingSchemaVersion($old, $schemas, $versions, null, $check)['version']);
        self::assertSame(1, XmlDescriptor::matchingSchemaVersion($old, array_reverse($schemas), $versions, null, $check)['version']);

        $refused = XmlDescriptor::matchingSchemaVersion($neither, $schemas, $versions, null, $check);
        self::assertNull($refused['version']);
        self::assertStringContainsString('( new )', $refused['errors'][0]);
        self::assertSame($refused, XmlDescriptor::matchingSchemaVersion($neither, array_reverse($schemas), $versions, null, $check));
        self::assertSame(['version' => null, 'errors' => ['no descriptor schema matches version 9']], XmlDescriptor::matchingSchemaVersion($old, $schemas, $versions, '9', $check));

        // Versions are numbers: 10 comes after 9, whatever the alphabet says.
        $tenth = ['9' => 'v1.xsd', '10' => 'v2.xsd'];
        self::assertStringContainsString('( new )', XmlDescriptor::matchingSchemaVersion($neither, $schemas, $tenth, null, $check)['errors'][0]);
    }

    /**
     * A reason is printed on one line, whatever a value or a path carried: a control
     * character (C0 or C1), a mark that reorders or hides text, a line separator, or a
     * byte that is not UTF-8 (a regular expression made for UTF-8 would give nothing).
     */
    public function testAReasonIsPrintable(): void
    {
        self::assertSame('plain', XmlDescriptor::printable('plain'));
        self::assertSame('a b?c?d?e', XmlDescriptor::printable("a\nb\tc\x7Fd\u{202E}e"));
        self::assertSame('a?b?c?d?e?f', XmlDescriptor::printable("a\u{85}b\u{9B}c\u{200F}d\u{2028}e\u{2066}f"));
        self::assertSame('Mod?', XmlDescriptor::printable("Mod\xE9"));
        self::assertNotSame('', XmlDescriptor::printable("\xE9"));

        file_put_contents($this->workDir."/ga\xE9rbage.xsd", 'garbage');
        $dom = new \DOMDocument();
        $dom->loadXML('<a/>');
        self::assertSame('the descriptor could not be checked against ga?rbage.xsd', XmlDescriptor::schemaErrors($dom, $this->workDir."/ga\xE9rbage.xsd")[0]);
    }
}
