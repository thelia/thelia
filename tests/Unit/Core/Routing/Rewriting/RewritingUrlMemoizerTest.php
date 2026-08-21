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

namespace Thelia\Tests\Unit\Core\Routing\Rewriting;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\Routing\Rewriting\RewritingUrlMemoizer;

final class RewritingUrlMemoizerTest extends TestCase
{
    public function testRememberOnlyComputesOnce(): void
    {
        $memoizer = new RewritingUrlMemoizer();
        $calls = 0;

        $compute = static function () use (&$calls): array {
            ++$calls;

            return ['url' => '/category?id=1', 'rewrittenUrl' => '/summer.html'];
        };

        $first = $memoizer->remember('category', 1, 'en_US', $compute);
        $second = $memoizer->remember('category', 1, 'en_US', $compute);

        self::assertSame($first, $second);
        self::assertSame(1, $calls, 'A second lookup for the same key must not recompute it.');
    }

    public function testRememberIsScopedByViewIdAndLocale(): void
    {
        $memoizer = new RewritingUrlMemoizer();
        $calls = 0;
        $compute = static function () use (&$calls): array {
            ++$calls;

            return ['url' => "/computed-{$calls}", 'rewrittenUrl' => null];
        };

        $memoizer->remember('category', 1, 'en_US', $compute);
        $memoizer->remember('category', 2, 'en_US', $compute);
        $memoizer->remember('category', 1, 'fr_FR', $compute);
        $memoizer->remember('brand', 1, 'en_US', $compute);

        self::assertSame(4, $calls, 'A different view, id or locale is a different key.');
    }

    public function testNegativeResultIsCachedTooAndCostsNoSecondComputation(): void
    {
        $memoizer = new RewritingUrlMemoizer();
        $calls = 0;

        $compute = static function () use (&$calls): array {
            ++$calls;

            // An object without a rewritten url still resolves to a plain view url.
            return ['url' => '/category?category_id=42', 'rewrittenUrl' => null];
        };

        $first = $memoizer->remember('category', 42, 'en_US', $compute);
        $second = $memoizer->remember('category', 42, 'en_US', $compute);

        self::assertNull($first['rewrittenUrl']);
        self::assertSame($first, $second);
        self::assertSame(1, $calls, 'A cached negative result must still cost zero computation on the next hit.');
        self::assertTrue($memoizer->has('category', 42, 'en_US'));
    }

    public function testHasReflectsWhatWasCachedIncludingNullValues(): void
    {
        $memoizer = new RewritingUrlMemoizer();

        self::assertFalse($memoizer->has('category', 1, 'en_US'));

        $memoizer->set('category', 1, 'en_US', null, null);

        // array_key_exists, not isset(): a stored null value is still a cache hit.
        self::assertTrue($memoizer->has('category', 1, 'en_US'));
    }

    public function testClearForgetsEveryCachedKey(): void
    {
        $memoizer = new RewritingUrlMemoizer();
        $memoizer->set('category', 1, 'en_US', '/category?category_id=1', '/summer.html');

        $memoizer->clear();

        self::assertFalse($memoizer->has('category', 1, 'en_US'));
    }

    public function testOnKernelRequestClearsOnlyForTheMainRequest(): void
    {
        $memoizer = new RewritingUrlMemoizer();
        $memoizer->set('category', 1, 'en_US', '/category?category_id=1', '/summer.html');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $subRequestEvent = new RequestEvent($kernel, new Request(), HttpKernelInterface::SUB_REQUEST);
        $memoizer->onKernelRequest($subRequestEvent);

        self::assertTrue($memoizer->has('category', 1, 'en_US'), 'A sub-request must not reset the cache of the request it is part of.');

        $mainRequestEvent = new RequestEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST);
        $memoizer->onKernelRequest($mainRequestEvent);

        self::assertFalse($memoizer->has('category', 1, 'en_US'));
    }

    public function testOnConsoleCommandClearsTheCache(): void
    {
        $memoizer = new RewritingUrlMemoizer();
        $memoizer->set('category', 1, 'en_US', '/category?category_id=1', '/summer.html');

        $event = new ConsoleCommandEvent(
            new Command('demo:command'),
            new ArrayInput([]),
            new NullOutput(),
        );

        $memoizer->onConsoleCommand($event);

        self::assertFalse($memoizer->has('category', 1, 'en_US'), 'A CLI command must start from an empty cache, batches of commands included.');
    }
}
