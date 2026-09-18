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

namespace Thelia\Tests\Unit\Core\Template;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateService;

/**
 * A front template may declare the template it extends, and ship only what it overrides.
 * Everything a caller resolves on disk - controllers, assets, icons - has to be looked up in
 * that template and then in the ones it inherits from, nearest first.
 *
 * The chain is read from the descriptors on disk on purpose: it is also read while the
 * container is compiled, where no Propel connection is booted, so it cannot go through
 * TemplateDefinition.
 */
final class TemplateChainTest extends TestCase
{
    private const TYPE = TemplateDefinition::FRONT_OFFICE_SUBDIR;

    private Filesystem $filesystem;

    /** @var list<string> */
    private array $createdTemplates = [];

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTemplates as $templateName) {
            $this->filesystem->remove(THELIA_TEMPLATE_DIR.self::TYPE.DS.$templateName);
        }

        $this->createdTemplates = [];
    }

    public function testATemplateWithoutParentIsAChainOfItsOwnDirectory(): void
    {
        $template = $this->createTemplate(null);

        self::assertSame(
            [THELIA_TEMPLATE_DIR.self::TYPE.DS.$template],
            TemplateService::getTemplateChainAbsolutePath(self::TYPE, $template),
        );
    }

    public function testATemplateThatDeclaresAParentCarriesItsParentDirectory(): void
    {
        $parent = $this->createTemplate(null);
        $child = $this->createTemplate($parent);

        self::assertSame(
            [
                THELIA_TEMPLATE_DIR.self::TYPE.DS.$child,
                THELIA_TEMPLATE_DIR.self::TYPE.DS.$parent,
            ],
            TemplateService::getTemplateChainAbsolutePath(self::TYPE, $child),
        );
    }

    public function testTheWholeAncestryIsWalkedNearestFirst(): void
    {
        $grandParent = $this->createTemplate(null);
        $parent = $this->createTemplate($grandParent);
        $child = $this->createTemplate($parent);

        self::assertSame(
            [
                THELIA_TEMPLATE_DIR.self::TYPE.DS.$child,
                THELIA_TEMPLATE_DIR.self::TYPE.DS.$parent,
                THELIA_TEMPLATE_DIR.self::TYPE.DS.$grandParent,
            ],
            TemplateService::getTemplateChainAbsolutePath(self::TYPE, $child),
        );
    }

    public function testAParentThatIsNotInstalledStopsTheChainInsteadOfBreakingIt(): void
    {
        $child = $this->createTemplate('a-template-that-was-never-installed');

        self::assertSame(
            [THELIA_TEMPLATE_DIR.self::TYPE.DS.$child],
            TemplateService::getTemplateChainAbsolutePath(self::TYPE, $child),
        );
    }

    public function testATemplateThatIsNotInstalledHasNoChainAtAll(): void
    {
        self::assertSame(
            [],
            TemplateService::getTemplateChainAbsolutePath(self::TYPE, 'a-template-that-was-never-installed'),
        );
    }

    public function testACycleBetweenTwoTemplatesTerminates(): void
    {
        $first = $this->uniqueTemplateName();
        $second = $this->uniqueTemplateName();

        $this->writeTemplate($first, $second);
        $this->writeTemplate($second, $first);

        self::assertSame(
            [
                THELIA_TEMPLATE_DIR.self::TYPE.DS.$first,
                THELIA_TEMPLATE_DIR.self::TYPE.DS.$second,
            ],
            TemplateService::getTemplateChainAbsolutePath(self::TYPE, $first),
        );
    }

    public function testATemplateWithoutDescriptorHasNoParent(): void
    {
        $template = $this->uniqueTemplateName();

        $this->createdTemplates[] = $template;
        $this->filesystem->mkdir(THELIA_TEMPLATE_DIR.self::TYPE.DS.$template);

        self::assertSame(
            [THELIA_TEMPLATE_DIR.self::TYPE.DS.$template],
            TemplateService::getTemplateChainAbsolutePath(self::TYPE, $template),
        );
    }

    private function createTemplate(?string $parent): string
    {
        $templateName = $this->uniqueTemplateName();

        $this->writeTemplate($templateName, $parent);

        return $templateName;
    }

    private function writeTemplate(string $templateName, ?string $parent): void
    {
        $this->createdTemplates[] = $templateName;

        $this->filesystem->dumpFile(
            THELIA_TEMPLATE_DIR.self::TYPE.DS.$templateName.DS.'template.xml',
            self::descriptor($templateName, $parent),
        );
    }

    private function uniqueTemplateName(): string
    {
        return uniqid('thelia-chain-test-', false);
    }

    private static function descriptor(string $templateName, ?string $parent): string
    {
        $parentElement = null === $parent ? '' : '<parent>'.$parent.'</parent>';

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <template xmlns="http://thelia.net/schema/dic/template">
                <descriptive locale="en">
                    <title>{$templateName}</title>
                </descriptive>
                {$parentElement}
                <languages>
                    <language>en_US</language>
                </languages>
                <version>1.0.0</version>
                <stability>prod</stability>
            </template>
            XML;
    }
}
