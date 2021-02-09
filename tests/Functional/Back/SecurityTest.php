<?php

namespace Thelia\Tests\Functional\Back;

use Thelia\Tests\Functional\WebTestCase;

class SecurityTest extends WebTestCase
{
    /**
     * @dataProvider protectedUrls
     */
    public function testAccessSecuredUrl(string $method, string $url): void
    {
        $client = static::createClient();

        $client->request($method, $url);

        self::assertEquals(403, $client->getResponse()->getStatusCode());
    }

    public function protectedUrls(): array
    {
        return [
            ['GET', '/admin/customers'],
            ['GET', '/admin/orders'],
            ['GET', '/admin/catalog'],
            ['GET', '/admin/folders'],
            ['GET', '/admin/modules']
        ];
    }
}
