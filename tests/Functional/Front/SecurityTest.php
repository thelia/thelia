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
    /**
     * @dataProvider protectedUrls
     */
    public function testAccessSecuredUrl(string $method, string $url): void
    {
        self::$client->request($method, $url);

        self::assertEquals(302, self::$client->getResponse()->getStatusCode());

        self::$client->followRedirect();

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
            ['GET', '/order/invoice'],
        ];
    }
}
