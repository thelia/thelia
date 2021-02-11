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
        $client = static::createClient();

        $client->request('GET', '/admin');

        $this->assertTrue($client->getResponse()->isRedirection());

        //$this->assertTrue($client->getResponse()->isRedirect('/admin/login'));
        $this->assertMatchesRegularExpression('/\/admin\/login$/', $client->getResponse()->headers->get('location'));

        $client->followRedirect();

        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $this->assertMatchesRegularExpression('/\/admin\/login$/', $client->getRequest()->getUri());
    }

    public function testLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/login');

        self::assertEquals(200, $client->getResponse()->getStatusCode());
    }
}
