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

class ConfigurationProductAttributeTest extends WebTestCase
{
    public function testIndex(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/configuration/attributes');

        self::assertResponseIsSuccessful();
    }

    public function testOpen(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/configuration/attributes/update?attribute_id=1');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/configuration/attributes/update?attribute_id=3');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/configuration/attributes/remove-from-all-templates');

        self::assertResponseRedirects();

        self::$client->request('GET', '/admin/configuration/attributes/add-to-all-templates');

        self::assertResponseRedirects();

        self::$client->request('GET', '/admin/configuration/attributes-av/update?attribute_id=3');

        self::assertResponseIsSuccessful();
    }
}
