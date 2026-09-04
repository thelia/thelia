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

namespace Thelia\Tests\Unit\Core\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * How long a refresh token stays valid belongs to the hosting, and the hosting
 * expects to set it in .env.local without rebuilding the container. Read from
 * $_ENV while the container is compiled, the value is frozen into the compiled
 * container instead, and whoever changed it sees nothing happen.
 */
final class RefreshTokenLifetimeConfigurationTest extends TestCase
{
    public function testTheLifetimeIsReadFromTheEnvironmentAtRuntime(): void
    {
        $parameters = $this->parameters();

        self::assertSame('%env(int:JWT_REFRESH_TOKEN_TTL)%', $parameters['thelia.security.jwt_refresh_token_ttl']);
        self::assertSame('2592000', $parameters['env(JWT_REFRESH_TOKEN_TTL)']);
    }

    public function testTheDefaultLifetimeIsThirtyDays(): void
    {
        self::assertSame(30 * 24 * 3600, (int) $this->parameters()['env(JWT_REFRESH_TOKEN_TTL)']);
    }

    /**
     * @return array<string, mixed>
     */
    private function parameters(): array
    {
        $container = new ContainerBuilder();
        $directory = \dirname(__DIR__, 4).'/core/lib/Thelia/Config/Resources';

        (new PhpFileLoader($container, new FileLocator($directory)))->load('services/core/security.php');

        return $container->getParameterBag()->all();
    }
}
