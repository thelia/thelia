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

namespace Thelia\Tests\Functional\Back;

use Thelia\Tests\Functional\WebTestCase;

class MessageTest extends WebTestCase
{
    public function testMessages(): void
    {
        $this->loginAdmin();

        self::$client->request('GET', '/admin/message/preview/4');

        self::assertResponseIsSuccessful();

        self::$client->request('GET', '/admin/message/preview/text/4');

        self::assertResponseIsSuccessful();

        self::$client->request('POST', '/admin/message/send/4');

        self::assertResponseIsSuccessful();
    }
}
