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
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\Validator\TemplateValidator;

/**
 * The descriptor schema makes <authors> optional, and a template that inherits everything
 * from another one is written down to almost nothing: a title, a parent, a version. Reading
 * such a descriptor must not need the elements the schema lets it leave out.
 */
final class TemplateValidatorOptionalElementsTest extends TestCase
{
    private string $templateDirectory;

    protected function setUp(): void
    {
        $this->templateDirectory = sys_get_temp_dir().'/thelia-template-validator-'.uniqid('', true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->templateDirectory);
    }

    public function testADescriptorThatNamesNoAuthorIsRead(): void
    {
        $this->writeDescriptor('');

        $descriptor = (new TemplateValidator($this->templateDirectory))
            ->getTemplateDefinition('a-template', TemplateDefinition::FRONT_OFFICE);

        self::assertSame([], $descriptor->getAuthors());
        self::assertSame('1.0.0', $descriptor->getVersion());
    }

    public function testTheAuthorsOfADescriptorThatNamesThemAreRead(): void
    {
        $this->writeDescriptor(
            <<<XML
                <authors>
                    <author>
                        <name>Thelia team</name>
                        <company>thelia.net</company>
                        <email>contact@thelia.net</email>
                        <website>https://thelia.net</website>
                    </author>
                </authors>
                XML,
        );

        $descriptor = (new TemplateValidator($this->templateDirectory))
            ->getTemplateDefinition('a-template', TemplateDefinition::FRONT_OFFICE);

        self::assertSame(
            [['Thelia team', 'thelia.net', 'contact@thelia.net', 'https://thelia.net']],
            $descriptor->getAuthors(),
        );
    }

    private function writeDescriptor(string $authorsElement): void
    {
        (new Filesystem())->dumpFile(
            $this->templateDirectory.DS.'template.xml',
            <<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <template xmlns="http://thelia.net/schema/dic/template">
                    <descriptive locale="en">
                        <title>A template</title>
                    </descriptive>
                    <languages>
                        <language>en_US</language>
                    </languages>
                    <version>1.0.0</version>
                    {$authorsElement}
                    <stability>prod</stability>
                </template>
                XML,
        );
    }
}
