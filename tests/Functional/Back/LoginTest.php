<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Functional\Back;

use Thelia\Tests\Functional\WebTestCase;

class LoginTest extends WebTestCase
{
    public function testRedirectionToLogin(): void
    {
        self::$client->request('GET', '/admin');

        $this->assertHeaderLocationUrl('/admin/login');

        self::$client->followRedirect();

        self::assertResponseIsSuccessful();

        $this->assertRequestUrl('/admin/login');
    }

    public function testLogin(): void
    {
        self::$client->request('GET', '/admin/login');

        self::assertResponseIsSuccessful();
    }
}
