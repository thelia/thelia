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
use Thelia\Core\Template\Exception\TemplateException;
use Thelia\Core\Template\InternalViewsDeclaration;

/**
 * A template declares the views it does not want served on a URL of their own name.
 *
 * Reading that declaration must tell "declares nothing" (null, so the caller keeps the
 * historical behaviour and exposes everything) apart from "declares an empty list".
 */
final class InternalViewsDeclarationTest extends TestCase
{
    private string $templateDirectory;

    protected function setUp(): void
    {
        $this->templateDirectory = sys_get_temp_dir().'/thelia-internal-views-'.uniqid('', true);

        (new Filesystem())->mkdir($this->templateDirectory.'/config');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->templateDirectory);
    }

    public function testATemplateWithoutTheFileDeclaresNothing(): void
    {
        (new Filesystem())->remove($this->templateDirectory.'/config');

        self::assertNull(InternalViewsDeclaration::readFrom($this->templateDirectory));
    }

    public function testDeclaredViewsAreReturnedInOrder(): void
    {
        $this->writeDeclaration("internal:\n    - checkout-delivery\n    - checkout-payment\n");

        self::assertSame(
            ['checkout-delivery', 'checkout-payment'],
            InternalViewsDeclaration::readFrom($this->templateDirectory),
        );
    }

    public function testAnEmptyListIsADeclaration(): void
    {
        $this->writeDeclaration("internal: []\n");

        self::assertSame([], InternalViewsDeclaration::readFrom($this->templateDirectory));
    }

    public function testViewNamesAreTrimmed(): void
    {
        $this->writeDeclaration("internal:\n    - '  address  '\n");

        self::assertSame(['address'], InternalViewsDeclaration::readFrom($this->templateDirectory));
    }

    public function testAFileWithoutTheInternalKeyDeclaresNothing(): void
    {
        $this->writeDeclaration("public:\n    - index\n");

        self::assertNull(InternalViewsDeclaration::readFrom($this->templateDirectory));
    }

    public function testAFileThatIsNotValidYamlIsReported(): void
    {
        $this->writeDeclaration("internal:\n  - unclosed: [\n");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageMatches('/is not a valid YAML file/');

        InternalViewsDeclaration::readFrom($this->templateDirectory);
    }

    public function testAListOfSomethingElseThanViewNamesIsReported(): void
    {
        $this->writeDeclaration("internal:\n    - address\n    - { view: category }\n");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageMatches('/must contain a list of view names/');

        InternalViewsDeclaration::readFrom($this->templateDirectory);
    }

    public function testAnInternalKeyThatIsNotAListIsReported(): void
    {
        $this->writeDeclaration("internal: address\n");

        $this->expectException(TemplateException::class);

        InternalViewsDeclaration::readFrom($this->templateDirectory);
    }

    private function writeDeclaration(string $content): void
    {
        (new Filesystem())->dumpFile(
            $this->templateDirectory.'/'.InternalViewsDeclaration::FILE_NAME,
            $content,
        );
    }
}
