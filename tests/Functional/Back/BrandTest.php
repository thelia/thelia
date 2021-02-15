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

class BrandTest extends WebTestCase
{
    public function testIndex(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/brand');

        self::assertResponseIsSuccessful();
    }

    public function testOpen(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/brand/update/1');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/brand/update/2');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/brand/update/3');

        self::assertResponseIsSuccessful();
    }
}
