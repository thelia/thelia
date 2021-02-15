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

class ExportTest extends WebTestCase
{
    public function testIndex(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/export');

        self::assertResponseIsSuccessful();
    }

    public function testOpen(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/export/1');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/export/3');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/export/6');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/export/7');

        self::assertResponseIsSuccessful();
    }
}
