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

namespace Thelia\Tests\Unit\Domain\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Webhook\WebhookDeliveryTransport;

final class WebhookDeliveryTransportTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function transports(): iterable
    {
        yield 'synchronous transport' => ['sync://', true];
        yield 'database queue' => ['doctrine://default', false];
        yield 'broker queue' => ['amqp://guest:guest@broker:5672/%2f/messages', false];
    }

    #[DataProvider('transports')]
    public function testTheTransportTellsWhetherTheRequestIsSentInline(string $dsn, bool $inline): void
    {
        self::assertSame($inline, (new WebhookDeliveryTransport($dsn))->runsInline());
    }
}
