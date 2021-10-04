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

class ConfigurationCurrencyTest extends WebTestCase
{
    public function testIndex(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/configuration/currencies');

        self::assertResponseIsSuccessful();
    }

    public function testOpen(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/configuration/currencies/update?currency_id=1');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/configuration/currencies/update?currency_id=2');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/configuration/currencies/update?currency_id=3');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/configuration/currencies/update-rates');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/configuration/currencies/set-default');

        self::assertResponseRedirects();

        self::$client->request('GET', '/admin/configuration/currencies/set-visible');

        self::assertResponseRedirects();
    }
}
