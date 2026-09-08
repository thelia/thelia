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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MonologBundle\DependencyInjection\Configuration;
use Symfony\Bundle\MonologBundle\DependencyInjection\MonologExtension;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Core\DependencyInjection\LoggingDefaults;

/**
 * What a shop writes in its config/packages about logging is what the shop
 * gets. The core only fills in what the shop said nothing about.
 */
final class LoggingDefaultsTest extends TestCase
{
    #[Test]
    public function aShopThatWritesNothingGetsTheLoggingOfTheCore(): void
    {
        $handlers = $this->handlersOf($this->container());

        self::assertSame('fingers_crossed', $handlers['main']['type']);
        self::assertSame('main_stream', $handlers['main']['handler']);
        self::assertArrayHasKey('security_rotating', $handlers);
        self::assertArrayHasKey('deprecations_rotating', $handlers);
        self::assertArrayHasKey('console', $handlers);
    }

    #[Test]
    public function aShopNamingWhereTheMainHandlerWritesIsObeyed(): void
    {
        $container = $this->container([
            'handlers' => [
                'main' => [
                    'type' => 'fingers_crossed',
                    'action_level' => 'error',
                    'handler' => 'nested',
                    'excluded_http_codes' => [404, 405],
                ],
                'nested' => [
                    'type' => 'stream',
                    'path' => 'php://stderr',
                    'level' => 'debug',
                ],
            ],
        ]);

        $handlers = $this->handlersOf($container);

        self::assertSame('nested', $handlers['main']['handler']);
        self::assertSame('php://stderr', $handlers['nested']['path']);
    }

    #[Test]
    public function aShopTurningTheMainHandlerIntoAnotherTypeGetsAValidConfiguration(): void
    {
        $container = $this->container([
            'handlers' => [
                'main' => [
                    'type' => 'stream',
                    'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                    'level' => 'debug',
                ],
            ],
        ]);

        $handlers = $this->handlersOf($container);

        self::assertSame('stream', $handlers['main']['type']);
        self::assertArrayNotHasKey(
            'main_stream',
            $handlers,
            'The handler the fingers-crossed default wrote through has no reason to survive it, and would put a second handler on the same file.',
        );
    }

    #[Test]
    public function whatTheShopSaidNothingAboutIsStillThere(): void
    {
        $container = $this->container([
            'handlers' => [
                'main' => [
                    'type' => 'stream',
                    'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                ],
            ],
        ]);

        $handlers = $this->handlersOf($container);

        self::assertSame('rotating_file', $handlers['security_rotating']['type']);
        self::assertSame('rotating_file', $handlers['deprecations_rotating']['type']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function handlersOf(ContainerBuilder $container): array
    {
        LoggingDefaults::prependTo($container);

        return (new Processor())->processConfiguration(
            new Configuration(),
            $container->getExtensionConfig('monolog'),
        )['handlers'];
    }

    /**
     * @param array<string, mixed> $applicationConfiguration what a config/packages/monolog.yaml holds
     */
    private function container(array $applicationConfiguration = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new MonologExtension());

        if ([] !== $applicationConfiguration) {
            $container->loadFromExtension('monolog', $applicationConfiguration);
        }

        return $container;
    }
}
