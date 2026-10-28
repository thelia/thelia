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

namespace Thelia\Tests\Integration\Domain\Ai\Tool;

use AiProbeModule\ProbeBothTool;
use AiProbeModule\ProbeDuplicateNameTool;
use AiProbeModule\ProbeReadTool;
use AiProbeModule\ProbeUndeclaredTool;
use AiProbeModule\ProbeUnsupportedKeywordTool;
use AiProbeModule\ProbeWriteTool;
use AiProbeModule\ProbeWrongPrefixTool;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Thelia\Core\DependencyInjection\Compiler\RegisterAiToolPass;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Tool\AiToolApprovalServiceInterface;
use Thelia\Domain\Ai\Tool\AiToolRegistryInterface;
use Thelia\Domain\Ai\Tool\Service\AiToolInstances;
use Thelia\Test\IntegrationTestCase;

/**
 * A service of a module that carries #[AsAiTool] is found, and a declaration that breaks a rule stops the build.
 */
final class AiToolDiscoveryTest extends IntegrationTestCase
{
    public static function setUpBeforeClass(): void
    {
        foreach (['ProbeTools', 'ProbeBadTools'] as $file) {
            require_once \dirname(__DIR__, 4).'/fixtures/ai/ProbeModule/'.$file.'.php';
        }
    }

    public function testTheKernelPublishesTheToolsOfTheCoreAndWiresTheRegistry(): void
    {
        $container = self::getContainer();

        $tools = $container->getParameter(RegisterAiToolPass::PARAMETER);
        self::assertIsArray($tools);
        self::assertArrayHasKey('thelia_search_products', $tools);
        self::assertArrayHasKey('thelia_update_product_price', $tools);
        self::assertInstanceOf(AiToolRegistryInterface::class, $container->get(AiToolRegistryInterface::class));
        self::assertInstanceOf(AiToolApprovalServiceInterface::class, $container->get(AiToolApprovalServiceInterface::class));

        $names = array_map(static fn ($descriptor): string => $descriptor->name, $container->get(AiToolRegistryInterface::class)->describe());
        self::assertContains('thelia_search_products', $names);
    }

    public function testTheToolsOfAnAutoconfiguredModuleServiceAreCollectedAndReachable(): void
    {
        $container = $this->compile([ProbeReadTool::class, ProbeWriteTool::class]);

        self::assertSame(['aiprobemodule_read' => ProbeReadTool::class, 'aiprobemodule_update' => ProbeWriteTool::class], $container->getParameter(RegisterAiToolPass::PARAMETER));
        self::assertInstanceOf(ProbeWriteTool::class, $container->get(AiToolInstances::class)->get('aiprobemodule_update'));
    }

    public function testAServiceWithoutTheAttributeDeclaresNothing(): void
    {
        self::assertSame([], $this->compile([\stdClass::class])->getParameter(RegisterAiToolPass::PARAMETER));
    }

    public function testTwoToolsWithTheSameNameStopTheBuild(): void
    {
        $this->expectException(InvalidAiArgumentException::class);
        $this->expectExceptionMessage('The AI tool "aiprobemodule_read" is declared twice, by "'.ProbeReadTool::class.'" and by "'.ProbeDuplicateNameTool::class.'"');

        $this->compile([ProbeReadTool::class, ProbeDuplicateNameTool::class]);
    }

    public function testAToolThatBreaksARuleStopsTheBuildWithTheReason(): void
    {
        $this->expectException(InvalidAiArgumentException::class);
        $this->expectExceptionMessage('must start with "aiprobemodule_"');

        $this->compile([ProbeWrongPrefixTool::class]);
    }

    public function testAToolWhoseSchemaUsesAKeywordThatIsNotCheckedStopsTheBuild(): void
    {
        $this->expectException(InvalidAiArgumentException::class);
        $this->expectExceptionMessageMatches('~tool "aiprobemodule_multiple" uses keywords the registry does not check.*/properties/quantity: multipleOf~');

        $this->compile([ProbeUnsupportedKeywordTool::class]);
    }

    public function testAToolThatImplementsBothInterfacesStopsTheBuild(): void
    {
        $this->expectException(InvalidAiArgumentException::class);
        $this->expectExceptionMessage('both interfaces');

        $this->compile([ProbeBothTool::class]);
    }

    public function testAToolWithoutItsAttributeStopsTheBuildInsteadOfStayingInvisible(): void
    {
        $this->expectException(InvalidAiArgumentException::class);
        $this->expectExceptionMessage('implements a tool interface but carries no #[AsAiTool]');

        $this->compile([ProbeUndeclaredTool::class]);
    }

    /**
     * @param list<class-string> $classes
     */
    private function compile(array $classes): ContainerBuilder
    {
        $container = new ContainerBuilder();

        // The very registration the kernel makes for every container it builds.
        $register = new \ReflectionMethod(self::$kernel, 'loadAutoConfigureInterfaces');
        $register->invoke(self::$kernel, $container);

        foreach ($classes as $class) {
            $container->setDefinition($class, (new Definition($class))->setAutoconfigured(true)->setPublic(true));
        }

        $container->setDefinition(AiToolInstances::class, (new Definition(AiToolInstances::class))->setPublic(true));
        $container->addCompilerPass(new RegisterAiToolPass());
        $container->compile();

        return $container;
    }
}
