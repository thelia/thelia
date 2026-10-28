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

namespace Thelia\Tests\Unit\Core\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Thelia\Core\Bundle\TheliaBundle;
use Thelia\Core\DependencyInjection\Extension\AiRecipeConfigurationExtension;

/**
 * The tests/fixtures/ai/recipes files are the config/packages files of the official Flex recipes of
 * symfony/ai-{mistral,anthropic,open-ai,generic,ollama}-platform (symfony/recipes, 0.1), copied as they are.
 */
final class AiRecipeConfigurationExtensionTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function recipes(): iterable
    {
        foreach (['mistral', 'anthropic', 'open_ai', 'generic', 'ollama'] as $bridge) {
            yield $bridge => [\sprintf('ai_%s_platform.yaml', $bridge)];
        }
    }

    #[DataProvider('recipes')]
    public function testTheConfigurationOfAnAiRecipeCompilesWithoutTheAiBundle(string $recipe): void
    {
        $container = $this->container();
        (new TheliaBundle())->build($container);

        $this->loadRecipes($container, [$recipe]);
        $this->compileAndDump($container);
    }

    public function testAllTheRecipesTogetherCompile(): void
    {
        $container = $this->container();
        (new TheliaBundle())->build($container);

        $this->loadRecipes($container, array_map(static fn (array $recipe): string => $recipe[0], iterator_to_array(self::recipes(), false)));
        $this->compileAndDump($container);
    }

    public function testTheKernelStillRefusesAnUnknownConfigurationKey(): void
    {
        $container = $this->container();
        (new TheliaBundle())->build($container);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no extension able to load the configuration for "not_ai"/');

        (new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 3).'/fixtures/ai/recipes')))->load('not_ai.yaml');
    }

    public function testTheExtensionGivesWayToTheOneOfTheAiBundle(): void
    {
        $container = $this->container();
        $bundleExtension = new class extends Extension {
            /** @var list<array<string, mixed>> */
            public array $received = [];

            public function getAlias(): string
            {
                return 'ai';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
                $this->received = $configs;
            }
        };
        $container->registerExtension($bundleExtension);

        (new TheliaBundle())->build($container);

        self::assertSame($bundleExtension, $container->getExtension('ai'));

        $this->loadRecipes($container, ['ai_mistral_platform.yaml']);
        $container->compile();

        self::assertStringContainsString('MISTRAL_API_KEY', $bundleExtension->received[0]['platform']['mistral']['api_key']);
    }

    public function testTheCoreExtensionOwnsTheAiAliasWhenNoOtherDoes(): void
    {
        $container = $this->container();
        (new TheliaBundle())->build($container);

        self::assertInstanceOf(AiRecipeConfigurationExtension::class, $container->getExtension('ai'));
    }

    /**
     * What the kernel does when it writes the container to the cache: the dump refuses an environment variable
     * that the configuration names and nothing reads ("Environment variables ... are never used").
     */
    private function compileAndDump(ContainerBuilder $container): void
    {
        $container->compile();

        self::assertNotSame('', (new PhpDumper($container))->dump(['class' => 'AiRecipeProbeContainer']));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        return $container;
    }

    /**
     * @param list<string> $files
     */
    private function loadRecipes(ContainerBuilder $container, array $files): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 3).'/fixtures/ai/recipes'));

        foreach ($files as $file) {
            $loader->load($file);
        }

        // Only what the recipes need: the extensions merge their configuration, no pass of the core runs.
        $passes = $container->getCompilerPassConfig();
        $passes->setMergePass(new MergeExtensionConfigurationPass(['ai']));
        $passes->setBeforeOptimizationPasses([]);
        $passes->setOptimizationPasses([]);
        $passes->setBeforeRemovingPasses([]);
        $passes->setRemovingPasses([]);
        $passes->setAfterRemovingPasses([]);
    }
}
