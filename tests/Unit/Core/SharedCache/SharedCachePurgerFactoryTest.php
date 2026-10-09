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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Thelia\Core\SharedCache\HttpSharedCachePurger;
use Thelia\Core\SharedCache\NullSharedCachePurger;
use Thelia\Core\SharedCache\SharedCachePurgerFactory;
use Thelia\Core\SharedCache\UnavailableSharedCachePurger;

final class SharedCachePurgerFactoryTest extends TestCase
{
    /** @var list<array{string, string, array<string, list<string>>}> */
    private array $requests = [];

    #[DataProvider('silentDrivers')]
    public function testWithoutAProxyNothingIsSent(string $driver): void
    {
        $purger = SharedCachePurgerFactory::create($driver, 'http://varnish', 'secret', $this->client());

        self::assertInstanceOf(NullSharedCachePurger::class, $purger);
        $purger->purge(['product-42']);
        self::assertSame([], $this->requests);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function silentDrivers(): iterable
    {
        yield 'empty' => [''];
        yield 'none' => ['none'];
        yield 'blank' => ['  '];
    }

    public function testVarnishReceivesOneXkeyPurgePerProxy(): void
    {
        $purger = SharedCachePurgerFactory::create('varnish', 'http://varnish-1:6081, http://varnish-2:6081', 's3cret', $this->client());

        self::assertInstanceOf(HttpSharedCachePurger::class, $purger);
        $purger->purge(['product-42', 'products']);

        self::assertCount(2, $this->requests);
        foreach ($this->requests as [$method, , $headers]) {
            self::assertSame('PURGE', $method);
            self::assertSame(['xkey: product-42 products'], $headers['xkey']);
            self::assertSame(['X-Thelia-Purge-Secret: s3cret'], $headers['x-thelia-purge-secret']);
        }
        self::assertSame(['http://varnish-1:6081/', 'http://varnish-2:6081/'], array_column($this->requests, 1));
    }

    #[DataProvider('surrogateDrivers')]
    public function testSouinAndSurrogateProxiesReceiveASurrogateKeyHeader(string $driver): void
    {
        $purger = SharedCachePurgerFactory::create($driver, 'http://proxy:80', '', $this->client());

        $purger->purge(['product-42', 'products']);

        self::assertCount(1, $this->requests);
        self::assertSame('PURGE', $this->requests[0][0]);
        self::assertSame(['Surrogate-Key: product-42, products'], $this->requests[0][2]['surrogate-key']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function surrogateDrivers(): iterable
    {
        yield 'souin' => ['souin'];
        yield 'surrogate-key' => ['surrogate-key'];
    }

    public function testWithoutASecretNoSecretHeaderIsSent(): void
    {
        $purger = SharedCachePurgerFactory::create('varnish', 'http://varnish:6081', '', $this->client());

        $purger->purge(['products']);

        self::assertArrayNotHasKey('x-thelia-purge-secret', $this->requests[0][2]);
    }

    public function testALongListIsSplitInBatchesThatFitTheHeader(): void
    {
        $purger = SharedCachePurgerFactory::create('varnish', 'http://varnish:6081', '', $this->client());
        $keys = [];
        for ($id = 1; $id <= 1500; ++$id) {
            $keys[] = 'product-'.$id;
        }

        $purger->purge($keys);

        self::assertGreaterThan(1, \count($this->requests));
        $sent = [];
        foreach ($this->requests as [, , $headers]) {
            self::assertLessThanOrEqual(8000, \strlen(substr($headers['xkey'][0], \strlen('xkey: '))));
            array_push($sent, ...explode(' ', substr($headers['xkey'][0], \strlen('xkey: '))));
        }
        self::assertSame($keys, $sent);
    }

    public function testNoKeyNoRequest(): void
    {
        SharedCachePurgerFactory::create('varnish', 'http://varnish:6081', '', $this->client())->purge([]);

        self::assertSame([], $this->requests);
    }

    public function testAnUnknownDriverFailsWhenAPurgeIsAttempted(): void
    {
        $purger = SharedCachePurgerFactory::create('fastly', 'http://proxy', '', $this->client());

        self::assertInstanceOf(UnavailableSharedCachePurger::class, $purger);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('"fastly" is unknown');

        $purger->purge(['product-42']);
    }

    public function testADriverWithoutAnyProxyFailsWhenAPurgeIsAttempted(): void
    {
        $purger = SharedCachePurgerFactory::create('varnish', ' , ', '', $this->client());

        self::assertInstanceOf(UnavailableSharedCachePurger::class, $purger);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('lists no proxy');

        $purger->purge(['product-42']);
    }

    private function client(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->requests[] = [$method, $url, $options['normalized_headers']];

            return new MockResponse('');
        });
    }
}
