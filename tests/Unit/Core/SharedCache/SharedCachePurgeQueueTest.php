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

namespace Thelia\Tests\Unit\Core\SharedCache;

use PHPUnit\Framework\TestCase;
use Thelia\Core\SharedCache\SharedCacheKey;
use Thelia\Core\SharedCache\SharedCachePurgeQueue;

final class SharedCachePurgeQueueTest extends TestCase
{
    public function testEveryKeyIsPurgedOnceInTheOrderItWasQueued(): void
    {
        $purger = new RecordingSharedCachePurger();
        $queue = new SharedCachePurgeQueue($purger);

        $queue->add(['product-42', 'products']);
        $queue->add(['products', 'category-7']);
        $queue->flush();

        self::assertSame([['product-42', 'products', 'category-7']], $purger->calls);
    }

    public function testTheKeyOfTheWholeShopReplacesTheOthers(): void
    {
        $purger = new RecordingSharedCachePurger();
        $queue = new SharedCachePurgeQueue($purger);

        $queue->add(['product-42']);
        $queue->add([SharedCacheKey::ALL]);
        $queue->add(['brand-3']);
        $queue->flush();

        self::assertSame([[SharedCacheKey::ALL]], $purger->calls);
    }

    public function testNothingQueuedSendsNothing(): void
    {
        $purger = new RecordingSharedCachePurger();

        (new SharedCachePurgeQueue($purger))->flush();

        self::assertSame([], $purger->calls);
    }

    public function testFlushingEmptiesTheQueue(): void
    {
        $purger = new RecordingSharedCachePurger();
        $queue = new SharedCachePurgeQueue($purger);
        $queue->add(['product-42']);

        $queue->flush();
        $queue->flush();

        self::assertCount(1, $purger->calls);
    }

    public function testAProxyThatFailsNeverBreaksTheRequest(): void
    {
        $purger = new RecordingSharedCachePurger(new \RuntimeException('proxy down'));
        $logger = new TestLogger();
        $queue = new SharedCachePurgeQueue($purger, $logger);
        $queue->add(['product-42']);

        $queue->flush();
        $queue->flush();

        self::assertCount(1, $purger->calls);
        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0][0]);
        self::assertStringContainsString('proxy down', $logger->records[0][1]);
    }

    public function testResettingForgetsWhatWasQueued(): void
    {
        $purger = new RecordingSharedCachePurger();
        $queue = new SharedCachePurgeQueue($purger);
        $queue->add(['product-42', SharedCacheKey::ALL]);

        $queue->reset();
        $queue->flush();

        self::assertSame([], $purger->calls);
    }
}
