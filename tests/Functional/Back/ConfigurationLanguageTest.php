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

class ConfigurationLanguageTest extends WebTestCase
{
    public function testIndex(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/configuration/languages');

        self::assertResponseIsSuccessful();
    }

    public function testLanguagesSubIndexes(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/configuration/languages/update/1');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/configuration/languages/update/2');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/configuration/languages/update/3');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/configuration/languages/update/4');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/configuration/languages/save/1');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/configuration/languages/save/2');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/configuration/languages/save/3');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/configuration/languages/domain/activate');

        self::assertResponseRedirects();

        self::$client->request('GET', '/admin/configuration/languages/domain/deactivate');

        self::assertResponseRedirects();

        self::$client->request('GET', '/admin/configuration/languages/updateUrl');

        self::assertResponseIsSuccessful();
    }
}
