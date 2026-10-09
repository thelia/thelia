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

namespace Thelia\Tests\Unit\Core\Template\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Thelia\Core\Template\Exception\InvalidDescriptorException;
use Thelia\Core\Template\Validator\TemplateDescriptorValidator;

final class TemplateDescriptorValidatorTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().'/thelia-template-descriptor-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->workDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->workDir);
    }

    /**
     * A version is given as text, and the table of schemas is read back with integer keys:
     * the version a descriptor is checked against is the one asked for.
     */
    public function testADescriptorIsCheckedAgainstTheVersionAskedFor(): void
    {
        $validator = new TemplateDescriptorValidator($this->writeDescriptor());

        self::assertSame($validator, $validator->validate('1'));

        try {
            $validator->validate('no-such-version');
            self::fail('No schema matches.');
        } catch (InvalidDescriptorException $refusal) {
            self::assertStringEndsWith(' : no descriptor schema matches version no-such-version', $refusal->getMessage());
        }
    }

    /**
     * A schema the server cannot read checks nothing: the descriptor is refused, never
     * passed, whether PHP turns the warning into an exception or not.
     */
    public function testADescriptorIsRefusedWhenItsSchemaCannotBeRead(): void
    {
        $schemas = $this->workDir.'/schemas';
        (new Filesystem())->mkdir($schemas);
        file_put_contents($schemas.'/template-1_0.xsd', 'garbage');
        $validator = new class($this->writeDescriptor(), $schemas) extends TemplateDescriptorValidator {
            public function __construct(string $descriptor, string $schemas)
            {
                parent::__construct($descriptor);
                $this->xsdFinder = (new Finder())->name('*.xsd')->in($schemas);
            }
        };

        $handler = static fn (): bool => false;
        set_error_handler($handler);
        $previousMode = libxml_use_internal_errors(true);

        try {
            $validator->validate();
            self::fail('A schema that cannot be read validates nothing.');
        } catch (InvalidDescriptorException $refusal) {
            self::assertStringContainsString('could not be checked against template-1_0.xsd', $refusal->getMessage());
            // The error handler, the error mode and the error buffer of libxml are the
            // caller's: given back as they were.
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
     * A descriptor that is not XML, or is missing, is refused with the reason, and with
     * no warning of PHP; the path stays, as the file is the theme's and the reader its
     * developer.
     */
    public function testADescriptorThatIsNotXmlIsRefusedWithAReason(): void
    {
        $path = $this->workDir.'/template.xml';
        file_put_contents($path, '<template><unclosed></template>');

        try {
            (new TemplateDescriptorValidator($path))->validate();
            self::fail('The descriptor is refused.');
        } catch (InvalidDescriptorException $refusal) {
            self::assertStringStartsWith($path.' file is not a valid template descriptor : ', $refusal->getMessage());
            self::assertStringContainsString('tag mismatch', $refusal->getMessage());
        }

        try {
            (new TemplateDescriptorValidator($this->workDir.'/gone.xml'))->validate();
            self::fail('The descriptor is refused.');
        } catch (InvalidDescriptorException $refusal) {
            self::assertStringEndsWith(' : it is not a readable file', $refusal->getMessage());
        }
    }

    private function writeDescriptor(): string
    {
        $path = $this->workDir.'/template.xml';

        file_put_contents($path, <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <template xmlns="http://thelia.net/schema/dic/template"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xsi:schemaLocation="http://thelia.net/schema/dic/template http://thelia.net/schema/dic/template/template-1_0.xsd">
                <descriptive locale="en_US">
                    <title>Descriptor sample</title>
                </descriptive>
                <languages>
                    <language>en_US</language>
                </languages>
                <version>1.0.0</version>
                <authors>
                    <author>
                        <name>Sample</name>
                        <company>Sample</company>
                        <email>sample@example.com</email>
                    </author>
                </authors>
                <thelia>3.0.0</thelia>
                <stability>prod</stability>
            </template>
            XML);

        return $path;
    }
}
