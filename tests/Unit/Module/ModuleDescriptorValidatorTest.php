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

namespace Thelia\Tests\Unit\Module;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Module\Exception\InvalidXmlDocumentException;
use Thelia\Module\ModuleDescriptorValidator;

/**
 * The module descriptor is a public format: every module repository writes one. The
 * element a module uses to ship inactive has to be accepted by the descriptor schema,
 * and a descriptor without it has to keep validating exactly as before.
 */
final class ModuleDescriptorValidatorTest extends TestCase
{
    private const int DESCRIPTOR_VERSION_2_2 = 3;

    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().'/thelia-module-descriptor-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->workDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->workDir);
    }

    public function testDescriptorDeclaringEnabledByDefaultValidatesAgainstTheCurrentSchema(): void
    {
        $validator = new ModuleDescriptorValidator();

        $descriptor = $validator->getDescriptor($this->writeDescriptor('<enabled-by-default>0</enabled-by-default>'));

        self::assertSame(self::DESCRIPTOR_VERSION_2_2, $validator->getModuleVersion());
        self::assertNotFalse($descriptor);
        self::assertSame('0', (string) $descriptor->{'enabled-by-default'});
    }

    public function testDescriptorWithoutEnabledByDefaultStillValidates(): void
    {
        $validator = new ModuleDescriptorValidator();

        $validator->getDescriptor($this->writeDescriptor(''));

        self::assertSame(self::DESCRIPTOR_VERSION_2_2, $validator->getModuleVersion());
    }

    public function testEnabledByDefaultOnlyAcceptsZeroOrOne(): void
    {
        $validator = new ModuleDescriptorValidator();

        $this->expectException(InvalidXmlDocumentException::class);

        $validator->validate($this->writeDescriptor('<enabled-by-default>maybe</enabled-by-default>'));
    }

    private function writeDescriptor(string $trailingElements): string
    {
        $path = $this->workDir.'/module.xml';

        file_put_contents($path, <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <module xmlns="http://thelia.net/schema/dic/module"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xsi:schemaLocation="http://thelia.net/schema/dic/module http://thelia.net/schema/dic/module/module-2_2.xsd">
                <fullnamespace>DescriptorSample\\DescriptorSample</fullnamespace>
                <descriptive locale="en_US">
                    <title>Descriptor sample</title>
                </descriptive>
                <languages>
                    <language>en_US</language>
                </languages>
                <version>1.0.0</version>
                <type>classic</type>
                <thelia>3.0.0</thelia>
                <stability>prod</stability>
                <mandatory>0</mandatory>
                <hidden>0</hidden>
                {$trailingElements}
            </module>
            XML);

        return $path;
    }
}
