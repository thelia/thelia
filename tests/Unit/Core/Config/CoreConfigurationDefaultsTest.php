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

namespace Thelia\Tests\Unit\Core\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * The kernel loads the core configuration files twice, the second time from
 * buildContainer(), after the shop's own config/packages/*.yaml. Whatever that
 * order, what the shop declares has to win: the core only ships defaults.
 */
final class CoreConfigurationDefaultsTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string, 2: array<string, mixed>, 3: string, 4: mixed}>
     */
    public static function coreConfigurationFileProvider(): iterable
    {
        yield 'api_platform title' => [
            'packages/api_platform.php',
            'api_platform',
            ['title' => 'Shop API'],
            'title',
            'Shop API',
        ];

        yield 'api_platform stateless default' => [
            'packages/api_platform.php',
            'api_platform',
            ['defaults' => ['stateless' => true]],
            'defaults',
            [
                'pagination_client_items_per_page' => true,
                'pagination_maximum_items_per_page' => 100,
                'stateless' => true,
            ],
        ];

        yield 'framework session save path' => [
            'packages/framework.php',
            'framework',
            ['session' => ['save_path' => '/srv/sessions']],
            'session',
            ['save_path' => '/srv/sessions'],
        ];
    }

    /**
     * @param array<string, mixed> $shopConfiguration
     */
    #[DataProvider('coreConfigurationFileProvider')]
    public function testTheShopConfigurationWinsOverTheCoreDefaults(
        string $file,
        string $alias,
        array $shopConfiguration,
        string $key,
        mixed $expected,
    ): void {
        $container = new ContainerBuilder();
        $container->registerExtension($this->createExtension($alias));

        // The shop's config/packages/*.yaml is loaded before the core re-imports
        // its own files, which is the order that used to hide it.
        $container->loadFromExtension($alias, $shopConfiguration);

        $directory = \dirname(__DIR__, 4).'/core/lib/Thelia/Config/Resources';
        (new PhpFileLoader($container, new FileLocator($directory)))->load($file);

        $merged = array_replace_recursive(...$container->getExtensionConfig($alias));

        self::assertSame($expected, $merged[$key]);
    }

    private function createExtension(string $alias): Extension
    {
        return new class($alias) extends Extension {
            public function __construct(private readonly string $alias)
            {
            }

            public function getAlias(): string
            {
                return $this->alias;
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        };
    }
}
