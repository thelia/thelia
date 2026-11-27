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

namespace Thelia\Tests\Api\Quote;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Model\QuoteQuery;
use Thelia\Tests\Api\Trait\RegistersGuestCustomers;

/**
 * Two lines a quote must not cross: a guest owns no quote, whatever address it typed, and the answer
 * about a quote is never kept in the cache the data access layer shares between visitors.
 */
final class QuoteGuestAndCacheApiTest extends QuoteApiTestCase
{
    use RegistersGuestCustomers;

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('operations')]
    public function testAGuestTokenIsRefusedEverywhereOnTheQuotes(string $method, string $uri, array $body): void
    {
        $this->enableGuestCheckout();
        $scenario = $this->quotedQuote();
        [, $guest] = $this->registerGuest();

        $response = $this->jsonRequest($method, str_replace('{id}', (string) $scenario['quote']->getId(), $uri), $body, $guest['token'], 'json');

        self::assertSame(403, $response->getStatusCode(), $method.' '.$uri);
        QuoteTableMap::clearInstancePool();
        self::assertSame('quoted', QuoteQuery::create()->findPk($scenario['quote']->getId())->getState());
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function operations(): iterable
    {
        yield 'the list' => ['GET', '/api/front/account/quotes', []];
        yield 'a quote' => ['GET', '/api/front/account/quotes/{id}', []];
        yield 'a request' => ['POST', '/api/front/account/quotes', ['cartId' => 1]];
        yield 'a counter-proposal' => ['POST', '/api/front/account/quotes/{id}/counter-offer', ['revision' => 2, 'lines' => [['quoteItemId' => 1, 'price' => '1']]]];
        yield 'an acceptance' => ['POST', '/api/front/account/quotes/{id}/accept', ['revision' => 2]];
        yield 'a refusal' => ['POST', '/api/front/account/quotes/{id}/reject', ['revision' => 2]];
    }

    public function testTheQuotesOfAnAccountAreNeverPartOfTheCacheOfTheDataAccessLayer(): void
    {
        /** @var list<string> $prefixes */
        $prefixes = static::getContainer()->getParameter('thelia.api.data_access.cache.allowed_prefixes');

        foreach (['/api/front/account/quotes', '/api/front/account/quotes/1'] as $path) {
            foreach ($prefixes as $prefix) {
                self::assertFalse(str_starts_with($path, $prefix), \sprintf('"%s" would be cached for everybody: the key holds the path, not the account.', $path));
            }
        }
    }
}
