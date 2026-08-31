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

namespace Thelia\Tests\Api\Contract;

use Thelia\Test\ApiTestCase;

/**
 * The front API has no default-deny: a resource is anonymous unless it names a
 * security rule. Two front resources used to hand sensitive data to any caller —
 * coupon codes (a secret distributed out of band) and the exact version of every
 * installed module (a fingerprint for targeting known CVEs). This contract keeps
 * them shut and fails loudly if a regression reopens either door.
 */
final class FrontResourceAuthorizationContractTest extends ApiTestCase
{
    public function testFrontCouponsRejectAnonymousAccess(): void
    {
        $this->createFixtureFactory()->coupon();

        $response = $this->jsonRequest('GET', '/api/front/coupons');

        self::assertSame(401, $response->getStatusCode(), 'Anonymous callers must not read coupon codes.');
    }

    public function testFrontCouponsAllowAuthenticatedCustomer(): void
    {
        $this->createFixtureFactory()->coupon();

        $response = $this->jsonRequest('GET', '/api/front/coupons', token: $this->authenticateAsCustomer());

        self::assertJsonResponseSuccessful($response);
    }

    public function testFrontModulesAreNotExposed(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/modules');

        self::assertSame(404, $response->getStatusCode(), 'The module list (and its versions) must not be reachable from the front API.');
    }
}
