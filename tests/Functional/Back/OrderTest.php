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

namespace Functional\Back;

use Thelia\Tests\Functional\WebTestCase;

class OrderTest extends WebTestCase
{
    public function testIndex(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/orders');

        self::assertResponseIsSuccessful();
    }

    public function testSubCategoryIndex(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/order/update/1');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/order/update/2');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/order/update/3');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/order/update/1/status');

        self::assertResponseStatusCodeSame(302);

        self::$client->request('GET', '/admin/order/update/1/address');

        self::assertResponseStatusCodeSame(302);

        self::$client->request('GET', '/admin/order/update/1/delivery-ref');

        self::assertResponseStatusCodeSame(302);
    }
}
