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

class ConfigurationAdminTest extends WebTestCase
{
    public function testIndex(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/configuration/administrators');

        self::assertResponseIsSuccessful();
    }

    public function testOpen(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/password-create-success');

        self::assertResponseStatusCodeSame(302);

        self::$client->request('GET', '/admin/set-email-address');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/password-created');

        self::assertResponseStatusCodeSame(302);

        self::$client->request('GET', '/admin/lost-password');

        self::assertResponseStatusCodeSame(302);
    }
}
