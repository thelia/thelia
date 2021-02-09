<?php

namespace Thelia\Tests\Functional\Front;

use Thelia\Tests\Functional\WebTestCase;

class IndexTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        self::assertEquals(200, $client->getResponse()->getStatusCode());
    }
}
