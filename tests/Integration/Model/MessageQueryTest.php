<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Integration\Model;

use Thelia\Model\MessageQuery;
use Thelia\Test\IntegrationTestCase;

final class MessageQueryTest extends IntegrationTestCase
{
    public function testGetFromNameReturnsAnExistingMessage(): void
    {
        self::assertSame('order_confirmation', MessageQuery::getFromName('order_confirmation')->getName());
    }

    public function testGetFromNameThrowsOnAnUnknownMessage(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to load message unknown_message_name.');

        MessageQuery::getFromName('unknown_message_name');
    }
}
