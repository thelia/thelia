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

namespace Thelia\Tests\Unit\Test\Volume;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\StatementInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Thelia\Test\Volume\Measure\QueryCountingClient;

final class QueryCountingClientTest extends TestCase
{
    private const FORM_PAGE = <<<'HTML'
        <html><body>
          <form action="/admin/search" method="get"><input name="q" value="ignored"></form>
          <form action="/admin/products/save?product_id=7" method="post">
            <input type="hidden" name="product[_token]" value="csrf-123">
            <input name="product[title]" value="Oak chair">
            <textarea name="product[description]">Solid oak</textarea>
            <select name="product[tax_rule]"><option value="1">Default</option><option value="2" selected>Reduced</option></select>
            <input type="checkbox" name="product[visible]" value="1" checked>
            <input type="checkbox" name="product[virtual]" value="1">
          </form>
        </body></html>
        HTML;

    public function testASaveSendsWhatTheFormHoldsAndCountsOnlyThePost(): void
    {
        $posted = null;
        $http = new MockHttpClient([
            new MockResponse(self::FORM_PAGE),
            static function (string $method, string $url, array $options) use (&$posted): MockResponse {
                $posted = ['method' => $method, 'url' => $url, 'body' => $options['body']];

                return new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => '/admin/products?page=1']]);
            },
        ]);
        // Counter readings: three pairs to learn what a reading costs (1), then before and after the
        // post, and one more reading that only moves by itself.
        $client = new QueryCountingClient($this->counter([10, 11, 20, 21, 30, 31, 100, 140, 141]), 'https://shop.test', $http, 0);

        $measure = $client->submit('/admin/products/update?product_id=7', '/admin/products/save', ['save_mode' => 'close'], '/admin/products', 'admin');

        self::assertSame(302, $measure->status);
        self::assertSame(39, $measure->queries);
        self::assertSame('POST', $posted['method']);
        self::assertSame('https://shop.test/admin/products/save?product_id=7', $posted['url']);
        parse_str($posted['body'], $body);
        self::assertSame([
            'product' => ['_token' => 'csrf-123', 'title' => 'Oak chair', 'description' => 'Solid oak', 'tax_rule' => '2', 'visible' => '1'],
            'save_mode' => 'close',
        ], $body);
    }

    public function testASaveThatRedirectsElsewhereIsRefused(): void
    {
        $http = new MockHttpClient([
            new MockResponse(self::FORM_PAGE),
            new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => '/admin/products/update?product_id=7']]),
        ]);
        $client = new QueryCountingClient($this->counter([10, 11, 20, 21, 30, 31, 100, 140, 141]), 'https://shop.test', $http, 0);

        $this->expectExceptionMessage('did not save');

        $client->submit('/admin/products/update?product_id=7', '/admin/products/save', [], '/admin/products', 'admin');
    }

    public function testAQueryTheShopSendsAfterTheResponseIsCounted(): void
    {
        $http = new MockHttpClient([
            new MockResponse('<html></html>'),
        ]);
        // After the body: 139 (the page's 38 and the reading), then 141 (a terminate listener
        // queried once more), then 142, which only moves by the reading itself.
        $client = new QueryCountingClient($this->counter([10, 11, 20, 21, 30, 31, 100, 139, 141, 142]), 'https://shop.test', $http, 0);

        self::assertSame(39, $client->measure('/')->queries);
    }

    /**
     * @param list<int> $readings successive values of the Questions counter
     */
    private function counter(array $readings): ConnectionInterface
    {
        $statement = $this->createStub(StatementInterface::class);
        $statement->method('fetch')->willReturnCallback(static function () use (&$readings): array {
            return ['Questions', (string) array_shift($readings)];
        });
        $connection = $this->createStub(ConnectionInterface::class);
        $connection->method('prepare')->willReturn($statement);

        return $connection;
    }
}
