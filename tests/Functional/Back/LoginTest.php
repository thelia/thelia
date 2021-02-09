<?php

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
