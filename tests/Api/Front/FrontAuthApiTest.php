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

namespace Thelia\Tests\Api\Front;

use Thelia\Test\ApiTestCase;

final class FrontAuthApiTest extends ApiTestCase
{
    public function testAdminLoginReturnsJwtToken(): void
    {
        $token = $this->authenticateAsAdmin();

        self::assertNotEmpty($token);
        self::assertSame(2, substr_count($token, '.'));
    }

    public function testAdminLoginWithBadCredentialsReturns401(): void
    {
        $response = $this->jsonRequest('POST', '/api/admin/login', [
            'username' => 'nonexistent',
            'password' => 'wrong',
        ]);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testFrontLoginWithBadCredentialsReturns401(): void
    {
        $response = $this->jsonRequest('POST', '/api/front/login', [
            'username' => 'nobody@example.com',
            'password' => 'wrong',
        ]);

        self::assertSame(401, $response->getStatusCode());
    }
}
