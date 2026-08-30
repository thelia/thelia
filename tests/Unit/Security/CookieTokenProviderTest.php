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

namespace Thelia\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Thelia\Core\Security\Token\CookieTokenProvider;

final class CookieTokenProviderTest extends TestCase
{
    public function testTheRememberMeCookieIsHardenedAgainstTheftAndForgery(): void
    {
        $options = $this->buildOptions(time() + 2592000, false);

        self::assertTrue($options['httponly'], 'the remember-me cookie must stay out of JavaScript reach');
        self::assertSame('Lax', $options['samesite']);
        self::assertSame('/', $options['path']);
    }

    public function testTheSecureFlagFollowsTheGivenConnection(): void
    {
        self::assertTrue($this->buildOptions(time(), true)['secure']);
        self::assertFalse($this->buildOptions(time(), false)['secure']);
    }

    public function testTheSecureFlagIsDeducedFromTheRequestWhenNotGiven(): void
    {
        // There is no HTTPS server context in CLI, so the cookie must not
        // require TLS: a plain HTTP setup would otherwise silently drop it.
        self::assertFalse($this->buildOptions(time(), null)['secure']);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOptions(int $expires, ?bool $secure): array
    {
        $provider = new class extends CookieTokenProvider {
            public function expose(int $expires, ?bool $secure): array
            {
                return $this->buildCookieOptions($expires, $secure);
            }
        };

        return $provider->expose($expires, $secure);
    }
}
