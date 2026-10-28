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

namespace Thelia\Tests\Unit\Domain\Ai\Tool;

use AiProbeModule\ProbeBothTool;
use AiProbeModule\ProbeModuleRightTool;
use AiProbeModule\ProbeOtherModuleTool;
use AiProbeModule\ProbeReadTool;
use AiProbeModule\ProbeReadWithUpdateTool;
use AiProbeModule\ProbeReservedPrefixTool;
use AiProbeModule\ProbeUndeclaredTool;
use AiProbeModule\ProbeUnknownResourceTool;
use AiProbeModule\ProbeWriteTool;
use AiProbeModule\ProbeWriteWithViewTool;
use AiProbeModule\ProbeWrongPrefixTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Tool\AiToolEffect;
use Thelia\Domain\Ai\Tool\Builtin\SearchProductsTool;
use Thelia\Domain\Ai\Tool\Builtin\UpdateProductPriceTool;
use Thelia\Domain\Ai\Tool\Internal\AiToolDeclaration;

/**
 * The rules of a declaration that need the class: prefix of the module, resource that exists, right of the
 * declaring module, effect deduced from the interface.
 */
final class AiToolDeclarationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        foreach (['ProbeTools', 'ProbeBadTools'] as $file) {
            require_once \dirname(__DIR__, 4).'/fixtures/ai/ProbeModule/'.$file.'.php';
        }
    }

    public function testTheEffectIsDeducedFromTheInterface(): void
    {
        self::assertSame(AiToolEffect::Read, AiToolDeclaration::fromClass(ProbeReadTool::class)->effect);
        self::assertSame(AiToolEffect::Write, AiToolDeclaration::fromClass(ProbeWriteTool::class)->effect);
    }

    public function testThePrefixIsTheRootNamespaceOfTheClassInLowerCase(): void
    {
        $declaration = AiToolDeclaration::fromClass(ProbeReadTool::class);

        self::assertSame('AiProbeModule', $declaration->module);
        self::assertSame('aiprobemodule', $declaration->prefix());
    }

    public function testAModuleMayGuardItsToolWithItsOwnRight(): void
    {
        self::assertSame('AiProbeModule', AiToolDeclaration::fromClass(ProbeModuleRightTool::class)->attribute->module);
    }

    public function testTheToolsOfTheCoreAreDeclaredUnderTheReservedPrefix(): void
    {
        self::assertSame('thelia_search_products', AiToolDeclaration::fromClass(SearchProductsTool::class)->name());
        self::assertSame('thelia_update_product_price', AiToolDeclaration::fromClass(UpdateProductPriceTool::class)->name());
        self::assertSame(AiToolEffect::Write, AiToolDeclaration::fromClass(UpdateProductPriceTool::class)->effect);
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function brokenTools(): iterable
    {
        yield 'the prefix of another module' => [ProbeWrongPrefixTool::class, 'must start with "aiprobemodule_"'];
        yield 'thelia is reserved to the core' => [ProbeReservedPrefixTool::class, 'must start with "aiprobemodule_"'];
        yield 'a resource that is no constant of AdminResources' => [ProbeUnknownResourceTool::class, 'not a constant of AdminResources'];
        yield 'the right of another module' => [ProbeOtherModuleTool::class, 'declared by the module "AiProbeModule"'];
        yield 'a write that asks for VIEW' => [ProbeWriteWithViewTool::class, 'CREATE, UPDATE or DELETE'];
        yield 'a read that asks for UPDATE' => [ProbeReadWithUpdateTool::class, 'only reads'];
        yield 'both interfaces' => [ProbeBothTool::class, 'both interfaces'];
        yield 'no attribute' => [ProbeUndeclaredTool::class, 'carries no #[AsAiTool]'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('brokenTools')]
    public function testADeclarationThatBreaksARuleStopsWithTheReason(string $class, string $message): void
    {
        $this->expectException(InvalidAiArgumentException::class);
        $this->expectExceptionMessage($message);

        AiToolDeclaration::fromClass($class);
    }
}
