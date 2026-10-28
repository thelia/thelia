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

namespace Thelia\Tests\Integration\Domain\Ai;

use AiProbeModule\ProbeDuplicate;
use AiProbeModule\ProbeWriter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Thelia\Core\DependencyInjection\Compiler\RegisterAiUsagePass;
use Thelia\Domain\Ai\AiUsageRegistryInterface;
use Thelia\Test\IntegrationTestCase;

/**
 * A service of a module that carries #[AsAiUsage] is found: the module skeleton loads its classes with
 * autoconfigure(true), which is what the definitions below ask for.
 */
final class AiUsageDiscoveryTest extends IntegrationTestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once \dirname(__DIR__, 3).'/fixtures/ai/ProbeModule/ProbeWriter.php';
        require_once \dirname(__DIR__, 3).'/fixtures/ai/ProbeModule/ProbeDuplicate.php';
    }

    public function testTheKernelPublishesTheUsagesAndTheRegistryIsWired(): void
    {
        $container = self::getContainer();

        self::assertIsArray($container->getParameter(RegisterAiUsagePass::PARAMETER));
        self::assertInstanceOf(AiUsageRegistryInterface::class, $container->get(AiUsageRegistryInterface::class));
    }

    public function testTheUsagesOfAnAutoconfiguredModuleServiceAreCollected(): void
    {
        $container = $this->compile([ProbeWriter::class]);

        self::assertSame(
            ['aiprobe.chat' => ProbeWriter::class, 'aiprobe.writing' => ProbeWriter::class],
            $container->getParameter(RegisterAiUsagePass::PARAMETER),
        );
    }

    public function testAServiceWithoutTheAttributeDeclaresNothing(): void
    {
        $container = $this->compile([\stdClass::class]);

        self::assertSame([], $container->getParameter(RegisterAiUsagePass::PARAMETER));
    }

    public function testTwoServicesDeclaringTheSameUsageStopTheBuild(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The AI usage "aiprobe.writing" is declared twice, by "'.ProbeWriter::class.'" and by "'.ProbeDuplicate::class.'"');

        $this->compile([ProbeWriter::class, ProbeDuplicate::class]);
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
            $container->setDefinition($class, (new Definition($class))->setAutoconfigured(true));
        }

        $container->addCompilerPass(new RegisterAiUsagePass());
        $container->compile();

        return $container;
    }
}
