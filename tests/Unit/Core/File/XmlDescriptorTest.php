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
        self::assertCount(1, $notChecked);
        self::assertStringStartsWith('the descriptor could not be checked against garbage.xsd (', $notChecked[0]);
    }
}
