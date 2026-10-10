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

namespace Thelia\Tests\Unit\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Thelia\Messenger\HeavyTransportDsnProcessor;

final class HeavyTransportDsnProcessorTest extends TestCase
{
    public function testWithoutAQueueTheHeavyJobsRunAtOnceToo(): void
    {
        self::assertSame('sync://', HeavyTransportDsnProcessor::heavyDsnOf(''));
    }

    public function testTheShopDatabaseQueueGetsAHeavyQueueOfItsOwn(): void
    {
        self::assertSame('doctrine://default?queue_name=heavy', HeavyTransportDsnProcessor::heavyDsnOf('doctrine://default'));
        self::assertSame('doctrine://default?redeliver_timeout=7200&queue_name=heavy', HeavyTransportDsnProcessor::heavyDsnOf('doctrine://default?redeliver_timeout=7200'));
        self::assertSame('doctrine://default?queue_name=heavy', HeavyTransportDsnProcessor::heavyDsnOf('doctrine://default?queue_name=jobs'));
    }

    public function testARedisQueueGetsAStreamOfItsOwn(): void
    {
        self::assertSame('redis://localhost:6379/messages_heavy', HeavyTransportDsnProcessor::heavyDsnOf('redis://localhost:6379/messages'));
        self::assertSame('redis://secret@redis:6379/shop_heavy/group/consumer?auto_setup=false', HeavyTransportDsnProcessor::heavyDsnOf('redis://secret@redis:6379/shop/group/consumer?auto_setup=false'));
        self::assertSame('redis://localhost:6379/messages_heavy', HeavyTransportDsnProcessor::heavyDsnOf('redis://localhost:6379'));
    }

    public function testAnyOtherQueueIsShared(): void
    {
        self::assertSame('amqp://guest:guest@localhost:5672/%2f/messages', HeavyTransportDsnProcessor::heavyDsnOf('amqp://guest:guest@localhost:5672/%2f/messages'));
    }

    public function testAnExplicitHeavyQueueWins(): void
    {
        $env = ['MESSENGER_TRANSPORT_DSN' => 'doctrine://default', 'MESSENGER_HEAVY_TRANSPORT_DSN' => 'amqp://broker/heavy'];

        self::assertSame('amqp://broker/heavy', (new HeavyTransportDsnProcessor())->getEnv('thelia_heavy_queue', 'MESSENGER_TRANSPORT_DSN', static fn (string $name): string => $env[$name]));
    }

    public function testAMissingVariableReadsAsNoQueue(): void
    {
        $getEnv = static fn (string $name): string => throw new EnvNotFoundException($name);

        self::assertSame('sync://', (new HeavyTransportDsnProcessor())->getEnv('thelia_heavy_queue', 'MESSENGER_TRANSPORT_DSN', $getEnv));
    }
}
