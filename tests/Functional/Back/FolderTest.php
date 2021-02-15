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

class FolderTest extends WebTestCase
{
    public function testIndex(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/folders');

        self::assertResponseIsSuccessful();
    }

    public function testSubFolderIndex(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/folders?parent=1');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/folders?parent=2');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/folders?parent=3');

        self::assertResponseIsSuccessful();
    }
}
