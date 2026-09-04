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

namespace Thelia\Tests\Unit\Core\Cache;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Two shops on one cache server, or the development and the production of the
 * same shop, must never read each other's keys. Symfony inlines the seed when
 * the container is compiled, so the guarantee lives in the configuration and
 * is checked there.
 */
final class ApplicationCacheConfigurationTest extends TestCase
{
    public function testEveryPoolIsStoredWhereTheHostingAsks(): void
    {
        self::assertSame('thelia.cache.adapter', $this->frameworkCache()['app']);
    }

    public function testTheKeyspaceIsSeededWithTheInstallPathAndTheEnvironment(): void
    {
        self::assertSame('%env(THELIA_CACHE_PREFIX_SEED)%', $this->frameworkCache()['prefix_seed']);

        // Resolved here rather than left as "%kernel.project_dir%": Symfony
        // escapes what it inlines into the seed, so a placeholder would reach
        // every shop as the same literal text.
        self::assertSame('/srv/shop/prod', $this->parameters()['env(THELIA_CACHE_PREFIX_SEED)']);
    }

    public function testTheRefreshTokensGetTheirOwnPool(): void
    {
        self::assertArrayHasKey('thelia.cache.security', $this->frameworkCache()['pools']);
    }

    public function testAnInstallationThatConfiguresNothingStaysOnItsOwnDisk(): void
    {
        $parameters = $this->parameters();

        self::assertSame('', $parameters['env(THELIA_CACHE_DSN)']);
    }

    public function testThePoolsCanBePruned(): void
    {
        $container = new ContainerBuilder();
        $this->load($container, 'services/core/cache.php');

        $class = $container->getDefinition('thelia.cache.adapter')->getClass();

        self::assertIsString($class);
        self::assertTrue(
            is_a($class, PruneableInterface::class, true),
            'CachePoolPrunerPass reads the class of the adapter, so cache:pool:prune only reaches the application pools if it is pruneable.',
        );
    }

    public function testThePoolsLiveOutsideTheContainerCacheDirectory(): void
    {
        $directory = $this->parameters()['thelia.cache.directory'];

        self::assertIsString($directory);
        self::assertStringNotContainsString('%kernel.cache_dir%', $directory);
    }

    /**
     * @return array<string, mixed>
     */
    private function frameworkCache(): array
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'framework';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });

        $this->load($container, 'packages/framework.php');

        return array_replace_recursive(...$container->getExtensionConfig('framework'))['cache'];
    }

    /**
     * @return array<string, mixed>
     */
    private function parameters(): array
    {
        $container = new ContainerBuilder();
        $this->load($container, 'parameters/application_cache.php');

        return $container->getParameterBag()->all();
    }

    private function load(ContainerBuilder $container, string $file): void
    {
        $container->setParameter('kernel.project_dir', '/srv/shop');
        $container->setParameter('kernel.environment', 'prod');

        $directory = \dirname(__DIR__, 4).'/core/lib/Thelia/Config/Resources';

        (new PhpFileLoader($container, new FileLocator($directory)))->load($file);
    }
}
