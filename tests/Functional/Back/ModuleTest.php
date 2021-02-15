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

class ModuleTest extends WebTestCase
{
    public function testIndex(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/modules');

        self::assertResponseIsSuccessful();
    }

    public function testOpen(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/module/update/7');

        self::assertResponseIsSuccessful();
    }
}
