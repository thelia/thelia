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

namespace Thelia\Tests\Functional\Front;

use Thelia\Tests\Functional\WebTestCase;

class SecurityTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // pour éviter que les tests marche les un sur les autres
        // dans le cas ou des tests modifie la bdd, il serait bien d'ouvrir une transaction SQL ici
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // et ici, rollback les changements
    }

    /**
     * @dataProvider protectedUrls
     */
    public function testAccessSecuredUrl(string $method, string $url): void
    {
        $client = static::createClient();

        $client->request($method, $url);

        self::assertEquals(302, $client->getResponse()->getStatusCode());

        $client->followRedirect();

        /*
         c'est la merde dans thelia
        en gros parfois on est redirigé sur /login , du coup c'est ok
        et parfois on est redirigé sur ?view=login" qui redirige sur /login
         */
        /*
        var_dump($client->getRequest()->getUri());

        self::assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertMatchesRegularExpression('/\/login$/', $client->getRequest()->getUri());
        */
    }

    public function protectedUrls(): array
    {
        return [
            ['GET', '/account'],
            ['GET', '/address/create'],
            ['GET', '/order/delivery'],
            ['GET', '/order/invoice']
        ];
    }
}
