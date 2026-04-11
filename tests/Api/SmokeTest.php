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
 * End-to-end smoke test for the test infrastructure itself.
 *
 * Exercises the full chain: kernel boot + `disableReboot`, JWT login,
 * transaction rollback, FixtureFactory, `ApiTestCase::jsonRequest`, and
 * the JSON-LD assertions in {@see \Thelia\Test\Trait\AssertsJsonApi}.
 *
 * When this file stays green, every subsequent API test can rely on the
 * same plumbing without reimplementing it.
 */
final class SmokeTest extends ApiTestCase
{
    public function testAdminJwtLoginReturnsToken(): void
    {
        $token = $this->authenticateAsAdmin();

        self::assertNotEmpty($token);
        // JWT is "header.payload.signature" — three base64url parts.
        self::assertSame(2, substr_count($token, '.'));
    }

    public function testAdminCanListProductsViaApi(): void
    {
        $token = $this->authenticateAsAdmin();

        $factory = $this->createFixtureFactory();
        $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
        );

        $response = $this->jsonRequest('GET', '/api/admin/products', token: $token);

        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(1, $response);
    }

    public function testRolledBackProductsAreNotLeakedAcrossTests(): void
    {
        // If the transaction from the previous test was not rolled back,
        // this collection would contain one product leftover.
        $token = $this->authenticateAsAdmin();

        $response = $this->jsonRequest('GET', '/api/admin/products', token: $token);

        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(0, $response);
    }
}
