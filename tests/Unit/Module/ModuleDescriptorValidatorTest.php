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
use Symfony\Component\Finder\Finder;
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

    /**
     * A module folder whose name reads as a URI ("Mod%41ule") is a folder: libxml, given
     * the path, decoded it and refused the descriptor as not found.
     */
    public function testAModuleInAFolderThatReadsAsAUriIsValidated(): void
    {
        $folder = $this->workDir.'/Mod%41ule/Config';
        (new Filesystem())->mkdir($folder);
        copy($this->writeDescriptor(''), $folder.'/module.xml');
        $validator = new ModuleDescriptorValidator();

        $descriptor = $validator->getDescriptor($folder.'/module.xml');

        self::assertSame(self::DESCRIPTOR_VERSION_2_2, $validator->getModuleVersion());
        self::assertNotFalse($descriptor);
        self::assertSame('Descriptor sample', (string) $descriptor->descriptive->title);
    }

    public function testDescriptorWithoutEnabledByDefaultStillValidates(): void
    {
        $validator = new ModuleDescriptorValidator();

        $validator->getDescriptor($this->writeDescriptor(''));

        self::assertSame(self::DESCRIPTOR_VERSION_2_2, $validator->getModuleVersion());
    }

    /**
     * A value written on its own line, as most descriptors format their elements, is the
     * same value: the schema must not refuse what the install reader accepts.
     */
    public function testEnabledByDefaultToleratesSurroundingWhitespace(): void
    {
        $validator = new ModuleDescriptorValidator();

        $descriptor = $validator->getDescriptor($this->writeDescriptor("<enabled-by-default>\n        0\n    </enabled-by-default>"));

        self::assertNotFalse($descriptor);
        self::assertSame('0', trim((string) $descriptor->{'enabled-by-default'}));
    }

    public function testEnabledByDefaultOnlyAcceptsZeroOrOne(): void
    {
        $validator = new ModuleDescriptorValidator();

        $this->expectException(InvalidXmlDocumentException::class);

        $validator->validate($this->writeDescriptor('<enabled-by-default>maybe</enabled-by-default>'));
    }

    /**
     * The refusal is shown to the administrator who uploads the module: it names what
     * is wrong, never where the server unpacked it.
     */
    public function testARefusedDescriptorNeverQuotesAPathOfTheServer(): void
    {
        try {
            (new ModuleDescriptorValidator())->validate($this->writeDescriptor('<enabled-by-default>maybe</enabled-by-default>'));
            self::fail('The descriptor is refused.');
        } catch (InvalidXmlDocumentException $refusal) {
            self::assertStringNotContainsString($this->workDir, $refusal->getMessage());
            self::assertStringContainsString('XML error', $refusal->getMessage());
        }
    }

    /**
     * The refusal names the module when the descriptor is at its place in one, the file
     * alone anywhere else (a descriptor checked on its own, as here): never the folder the
     * server happened to unpack it in.
     */
    public function testARefusedDescriptorIsNamedByItsModuleOnlyAtItsPlaceInOne(): void
    {
        $validator = new ModuleDescriptorValidator();

        try {
            $validator->validate($this->writeDescriptor('<enabled-by-default>maybe</enabled-by-default>'));
            self::fail('The descriptor is refused.');
        } catch (InvalidXmlDocumentException $refusal) {
            self::assertStringStartsWith('The module.xml is not a valid file', $refusal->getMessage());
        }

        $inModule = $this->workDir.'/DescriptorSample/Config/module.xml';
        (new Filesystem())->mkdir(\dirname($inModule));
        rename($this->writeDescriptor('<enabled-by-default>maybe</enabled-by-default>'), $inModule);

        try {
            $validator->validate($inModule);
            self::fail('The descriptor is refused.');
        } catch (InvalidXmlDocumentException $refusal) {
            self::assertStringStartsWith('The module.xml of DescriptorSample is not a valid file', $refusal->getMessage());
        }
    }

    /**
     * A descriptor that is not XML at all is refused with a reason, not with nothing.
     */
    public function testADescriptorThatIsNotXmlIsRefusedWithAReason(): void
    {
        $path = $this->workDir.'/module.xml';
        file_put_contents($path, '<module><unclosed></module>');

        try {
            (new ModuleDescriptorValidator())->validate($path);
            self::fail('The descriptor is refused.');
        } catch (InvalidXmlDocumentException $refusal) {
            self::assertStringStartsWith('The module.xml is not a valid file: ', $refusal->getMessage());
            self::assertStringContainsString('tag mismatch', $refusal->getMessage());
            self::assertMatchesRegularExpression('/ \(Code \d+\) on line 1/', $refusal->getMessage());
            self::assertStringNotContainsString($this->workDir, $refusal->getMessage());
        }
    }

    /**
     * A descriptor the server could not open is refused by its name: libxml quotes the
     * path it failed on, the refusal never does.
     */
    public function testADescriptorThatCannotBeOpenedIsRefusedWithoutItsPath(): void
    {
        foreach ([$this->workDir.'/Sample/Config/module.xml', $this->workDir.'/./Sample/Config/module.xml', '', "a\0b", "dir\0x/Config/module.xml", $this->workDir."/Mod\xE9/Config/module.xml"] as $notReadable) {
            try {
                (new ModuleDescriptorValidator())->validate($notReadable);
                self::fail('The descriptor is refused.');
            } catch (InvalidXmlDocumentException $refusal) {
                self::assertStringEndsWith(' is not a valid file: it is not a readable file', $refusal->getMessage());
                self::assertStringNotContainsString($this->workDir, $refusal->getMessage());
                self::assertSame(0, preg_match('/[\x00-\x1F\x7F]|The {2,}is/', $refusal->getMessage()));
                self::assertSame(1, preg_match('//u', $refusal->getMessage()));
            }
        }
    }

    public function testADescriptorTheAccountCannotReadIsRefusedAsSuch(): void
    {
        $unreadable = $this->workDir.'/Unreadable/Config/module.xml';
        (new Filesystem())->mkdir(\dirname($unreadable));
        file_put_contents($unreadable, '<module/>');
        chmod($unreadable, 0);

        if (is_readable($unreadable)) {
            self::markTestSkipped('The account running the tests reads anything: a file nobody may read cannot be made.');
        }

        try {
            (new ModuleDescriptorValidator())->validate($unreadable);
            self::fail('The descriptor is refused.');
        } catch (InvalidXmlDocumentException $refusal) {
            self::assertSame('The module.xml of Unreadable is not a valid file: it is not a readable file', $refusal->getMessage());
        }
    }

    /**
     * A version is given as text, and the table of schemas is read back with integer keys:
     * the version a descriptor is checked against is the one asked for.
     */
    public function testADescriptorIsCheckedAgainstTheVersionAskedFor(): void
    {
        $validator = new ModuleDescriptorValidator();

        self::assertTrue($validator->validate($this->writeDescriptor(''), (string) self::DESCRIPTOR_VERSION_2_2));
        self::assertSame(self::DESCRIPTOR_VERSION_2_2, (int) $validator->getModuleVersion());

        // What only the 2.2 schema accepts is refused against the first one.
        try {
            $validator->validate($this->writeDescriptor('<enabled-by-default>1</enabled-by-default>'), '1');
            self::fail('The first schema does not know enabled-by-default.');
        } catch (InvalidXmlDocumentException $refusal) {
            self::assertStringContainsString('XML error', $refusal->getMessage());
        }
    }

    /**
     * A folder given as a descriptor is refused as not a file, with no warning of PHP.
     */
    public function testAFolderIsNotADescriptor(): void
    {
        try {
            (new ModuleDescriptorValidator())->validate($this->workDir);
            self::fail('A folder is refused.');
        } catch (InvalidXmlDocumentException $refusal) {
            self::assertStringEndsWith('is not a valid file: it is not a readable file', $refusal->getMessage());
            self::assertStringNotContainsString($this->workDir, $refusal->getMessage());
        }
    }

    /**
     * A schema the server cannot read checks nothing: the descriptor is refused, never
     * passed, and the refusal names the schema, never where it is. Whether PHP turns the
     * warning into an exception, as the development environment does, or not.
     */
    public function testADescriptorIsRefusedWhenItsSchemaCannotBeRead(): void
    {
        $schemas = $this->workDir.'/schemas';
        (new Filesystem())->mkdir($schemas);
        file_put_contents($schemas.'/module-2_2.xsd', 'garbage');
        $descriptor = $this->writeDescriptor('');

        try {
            $this->validatorWithSchemasIn($schemas)->validate($descriptor);
            self::fail('A schema that cannot be read validates nothing.');
        } catch (InvalidXmlDocumentException $refusal) {
            self::assertStringContainsString('could not be checked against module-2_2.xsd', $refusal->getMessage());
            self::assertStringNotContainsString($this->workDir, $refusal->getMessage());
        }
    }

    /**
     * A schema PHP cannot read for the account: the refusal names the schema, never its
     * folder, out of what PHP said.
     */
    public function testADescriptorIsRefusedWhenItsSchemaCannotBeReadByTheAccount(): void
    {
        $schemas = $this->workDir.'/schemas';
        (new Filesystem())->mkdir($schemas);
        file_put_contents($schemas.'/module-2_2.xsd', 'garbage');
        chmod($schemas.'/module-2_2.xsd', 0);
        $descriptor = $this->writeDescriptor('');

        if (is_readable($schemas.'/module-2_2.xsd')) {
            self::markTestSkipped('The account running the tests reads anything: a file nobody may read cannot be made.');
        }

        try {
            $this->validatorWithSchemasIn($schemas)->validate($descriptor);
            self::fail('A schema that cannot be read validates nothing.');
        } catch (InvalidXmlDocumentException $refusal) {
            self::assertStringContainsString('could not be checked against module-2_2.xsd', $refusal->getMessage());
            self::assertStringContainsString('file_get_contents(module-2_2.xsd)', $refusal->getMessage());
            self::assertStringNotContainsString($this->workDir, $refusal->getMessage());
        }
    }

    /**
     * Without a version asked for, the refusal tells what the latest schema said.
     */
    public function testARefusalTellsWhatTheLatestSchemaSaid(): void
    {
        try {
            (new ModuleDescriptorValidator())->validate($this->writeDescriptor('<enabled-by-default>7</enabled-by-default>'));
            self::fail('The descriptor is refused.');
        } catch (InvalidXmlDocumentException $refusal) {
            self::assertStringContainsString("not an element of the set {'0', '1'}", $refusal->getMessage());
        }
    }

    /**
     * A value of the descriptor quoted by the schema is shown without what would make a
     * log or a page obey it.
     */
    public function testARefusalQuotesAValueOfTheDescriptorPrintably(): void
    {
        try {
            (new ModuleDescriptorValidator())->validate($this->writeDescriptor("<enabled-by-default>a\nb\u{202E}c</enabled-by-default>"));
            self::fail('The descriptor is refused.');
        } catch (InvalidXmlDocumentException $refusal) {
            self::assertStringContainsString("'a b?c'", $refusal->getMessage());
            self::assertSame(0, preg_match('/[\x00-\x1F\x7F\x{202E}]/u', $refusal->getMessage()));
        }
    }

    /**
     * The error handler and the error mode of libxml are the caller's, given back as they
     * were, a refusal or not; the error buffer of libxml is left empty.
     */
    public function testTheErrorHandlingOfTheCallerIsGivenBack(): void
    {
        $schemas = $this->workDir.'/schemas';
        (new Filesystem())->mkdir($schemas);
        file_put_contents($schemas.'/module-2_2.xsd', 'garbage');
        $handler = static fn (): bool => false;
        set_error_handler($handler);
        $previousMode = libxml_use_internal_errors(true);

        try {
            self::assertTrue((new ModuleDescriptorValidator())->validate($this->writeDescriptor('')));
            self::assertTrue(libxml_use_internal_errors());
            self::assertSame($handler, set_error_handler(null));
            restore_error_handler();

            foreach ([$this->writeDescriptor('<enabled-by-default>maybe</enabled-by-default>'), $this->workDir.'/gone.xml'] as $refused) {
                try {
                    (new ModuleDescriptorValidator())->validate($refused);
                } catch (InvalidXmlDocumentException) {
                }
            }

            try {
                $this->validatorWithSchemasIn($schemas)->validate($this->writeDescriptor(''));
            } catch (InvalidXmlDocumentException) {
            }

            self::assertTrue(libxml_use_internal_errors());
            self::assertSame([], libxml_get_errors());
            self::assertSame($handler, set_error_handler(null));
            restore_error_handler();
        } finally {
            libxml_use_internal_errors($previousMode);
            restore_error_handler();
        }
    }

    /**
     * A server with no schema at all says so, rather than that the descriptor is not XML.
     */
    public function testAServerWithoutASchemaSaysSo(): void
    {
        $schemas = $this->workDir.'/no-schemas';
        (new Filesystem())->mkdir($schemas);

        try {
            $this->validatorWithSchemasIn($schemas)->validate($this->writeDescriptor(''));
            self::fail('No schema matches.');
        } catch (InvalidXmlDocumentException $refusal) {
            self::assertStringEndsWith('no descriptor schema matches version any', $refusal->getMessage());
        }
    }

    private function validatorWithSchemasIn(string $folder): ModuleDescriptorValidator
    {
        return new class($folder) extends ModuleDescriptorValidator {
            public function __construct(string $folder)
            {
                parent::__construct();
                $this->xsdFinder = (new Finder())->name('*.xsd')->in($folder);
            }
        };
    }

    /**
     * A well-formed descriptor checked against a version no schema has is told so, never
     * that it is not XML.
     */
    public function testAVersionWithoutASchemaIsNamedAsTheReason(): void
    {
        try {
            (new ModuleDescriptorValidator())->validate($this->writeDescriptor(''), 'no-such-version');
            self::fail('No schema matches.');
        } catch (InvalidXmlDocumentException $refusal) {
            self::assertSame('The module.xml is not a valid file: no descriptor schema matches version no-such-version', $refusal->getMessage());
        }
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
