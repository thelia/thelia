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

class ConfigurationProductTemplateTest extends WebTestCase
{
    public function testIndex(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/configuration/templates');

        self::assertResponseIsSuccessful();
    }

    public function testOpen(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/configuration/templates/update?template_id=1');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/configuration/templates/duplicate');

        self::assertResponseRedirects();

        self::$client->request('GET', '/admin/configuration/templates/features/list');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/configuration/templates/attributes/list');

        self::assertResponseIsSuccessful();
    }
}
