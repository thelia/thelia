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

namespace Thelia\Tests\Api;

use Thelia\Test\ApiTestCase;

/**
 * Signing in through the API triggers no deprecation of the security component:
 * since Symfony 7.3, a user class that still has its eraseCredentials() called
 * on login is reported, once per login.
 */
final class LoginDeprecationApiTest extends ApiTestCase
{
    public function testACustomerLoginTriggersNoDeprecation(): void
    {
        self::assertSame([], $this->deprecationsOf(fn (): string => $this->authenticateAsCustomer()));
    }

    public function testAnAdministratorLoginTriggersNoDeprecation(): void
    {
        self::assertSame([], $this->deprecationsOf(fn (): string => $this->authenticateAsAdmin()));
    }

    /**
     * @return list<string>
     */
    private function deprecationsOf(callable $login): array
    {
        $deprecations = [];

        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            if (str_contains($message, 'eraseCredentials')) {
                $deprecations[] = $message;
            }

            return true;
        }, \E_DEPRECATED | \E_USER_DEPRECATED);

        try {
            $login();
        } finally {
            restore_error_handler();
        }

        return $deprecations;
    }
}
