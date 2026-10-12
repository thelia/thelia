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
use Thelia\Core\Template\Exception\TemplateException;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\Validator\TemplateDescriptor;
use Thelia\Core\Template\Validator\TemplateValidator;

/**
 * Reading a template.xml gives the descriptor the back-office shows for the template, and refuses
 * a template that asks for a newer Thelia than the one installed.
 */
final class TemplateValidatorTest extends TestCase
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

    public function testATemplateThatNeedsANewerTheliaIsRefused(): void
    {
        $this->writeDescriptor(theliaVersion: '99.0.0');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('The template "a-template" requires Thelia 99.0.0 or newer');

        $this->descriptor();
    }

    public function testATemplateThatNeedsAnOlderTheliaIsRead(): void
    {
        $this->writeDescriptor(theliaVersion: '3.0.0');

        self::assertSame('3.0.0', $this->descriptor()->getTheliaVersion());
    }

    public function testATemplateThatNamesNoTheliaVersionIsRead(): void
    {
        $this->writeDescriptor();

        self::assertSame('', $this->descriptor()->getTheliaVersion());
    }

    public function testEveryLanguageOfTheTemplateIsListed(): void
    {
        $this->writeDescriptor(languages: ['en_US', 'fr_FR', 'de_DE']);

        self::assertSame(['en_US', 'fr_FR', 'de_DE'], $this->descriptor()->getLanguages());
    }

    public function testEveryDescriptiveOfTheTemplateIsReadByLocale(): void
    {
        $this->writeDescriptor(descriptives: <<<XML
            <descriptive locale="en_US">
                <title>Flexy</title>
                <subtitle>The default theme</subtitle>
                <description>A responsive front-office.</description>
                <postscriptum>Made in Lyon</postscriptum>
            </descriptive>
            <descriptive locale="fr_FR">
                <title>Flexy FR</title>
            </descriptive>
            XML);

        self::assertSame(
            [
                'en_US' => [
                    'title' => 'Flexy',
                    'subtitle' => 'The default theme',
                    'description' => 'A responsive front-office.',
                    'postscriptum' => 'Made in Lyon',
                ],
                'fr_FR' => [
                    'title' => 'Flexy FR',
                    'subtitle' => '',
                    'description' => '',
                    'postscriptum' => '',
                ],
            ],
            $this->descriptor()->getDescriptives(),
        );
    }

    public function testEveryAuthorOfTheTemplateIsListed(): void
    {
        $this->writeDescriptor(authors: <<<XML
            <authors>
                <author>
                    <name>Ada</name>
                    <company>Analytical</company>
                    <email>ada@example.com</email>
                    <website>https://example.com/ada</website>
                </author>
                <author>
                    <name>Grace</name>
                    <email>grace@example.com</email>
                </author>
            </authors>
            XML);

        self::assertSame(
            [
                ['Ada', 'Analytical', 'ada@example.com', 'https://example.com/ada'],
                ['Grace', '', 'grace@example.com', ''],
            ],
            $this->descriptor()->getAuthors(),
        );
    }

    private function descriptor(): TemplateDescriptor
    {
        return (new TemplateValidator($this->templateDirectory))
            ->getTemplateDefinition('a-template', TemplateDefinition::FRONT_OFFICE);
    }

    /**
     * @param list<string> $languages
     */
    private function writeDescriptor(
        string $theliaVersion = '',
        array $languages = ['en_US'],
        string $descriptives = '<descriptive locale="en_US"><title>A template</title></descriptive>',
        string $authors = '',
    ): void {
        $languageElements = implode('', array_map(static fn (string $language): string => "<language>{$language}</language>", $languages));
        $theliaElement = '' === $theliaVersion ? '' : "<thelia>{$theliaVersion}</thelia>";

        (new Filesystem())->dumpFile(
            $this->templateDirectory.DS.'template.xml',
            <<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <template xmlns="http://thelia.net/schema/dic/template">
                    {$descriptives}
                    <languages>{$languageElements}</languages>
                    <version>1.0.0</version>
                    {$authors}
                    {$theliaElement}
                    <stability>prod</stability>
                </template>
                XML,
        );
    }
}
