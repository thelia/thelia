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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Thelia\Core\Cache\ConfigCacheService;

/**
 * Everything in the configuration pool is dropped by whatever writes what it
 * was built from, and the pool itself goes away with the cache directory. An
 * expiry on top of that only buys a re-read of an answer that has not changed.
 */
final class ConfigCachePoolLifetimeTest extends TestCase
{
    #[Test]
    public function theConfigurationPoolHasNoExpiry(): void
    {
        self::assertSame(0, $this->poolArguments()[1]);
    }

    #[Test]
    public function thePoolTheContainerBuildsIsTheOneReadBeforeThereIsAContainer(): void
    {
        self::assertSame(
            ConfigCacheService::CACHE_NAMESPACE,
            $this->poolArguments()[0],
            'The namespace is a literal here, and warmFromSharedEntry() has to name the same one: '
            .'a value pulled from the container would not exist before there is one.',
        );
    }

    #[Test]
    public function thePoolFallsBackUnderTheContainerCacheDirectory(): void
    {
        self::assertSame(
            '%kernel.cache_dir%',
            $this->poolArguments()[3],
            'The local fallback used when THELIA_CACHE_DSN is empty must go away with cache:clear, '
            .'the one safety net left for a row changed outside ConfigQuery::write().',
        );
    }

    /**
     * @return array<int, mixed>
     */
    private function poolArguments(): array
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader(
            $container,
            new FileLocator(THELIA_LIB.'Config/Resources/services/core'),
            'prod',
        ))->load('cache.php');

        return $container->getDefinition('thelia.cache.config.adapter')->getArguments();
    }
}
