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

namespace Thelia\Tests\Http\Flexy\Quote;

use Symfony\Component\DomCrawler\Crawler;

/**
 * "My quotes": the entry of the menu of the account, and the list of the quotes of the buyer, paged by
 * the API, with the state each quote reads in.
 */
final class QuoteListTest extends QuoteFlexyTestCase
{
    public function testTheMenuOfTheAccountLeadsToTheQuotesWhenTheShopTakesQuotes(): void
    {
        foreach (['alongside', 'only'] as $mode) {
            $this->setQuoteMode($mode);
            $this->signInAs($this->customer());

            self::assertContains('/account/quotes', $this->subheaderLinks($this->get('/account')), $mode);
        }
    }

    public function testTheMenuHasNoEntryWhenQuotesAreOff(): void
    {
        $this->setQuoteMode('disabled');
        $this->signInAs($this->customer());

        self::assertNotContains('/account/quotes', $this->subheaderLinks($this->get('/account')));
    }

    public function testTheListShowsTheReferenceTheStateTheValidityAndTheDateOfEachQuote(): void
    {
        $scenario = $this->quotedQuote(null, null, new \DateTimeImmutable('+10 days'));
        $this->signInAs($scenario['buyer']);

        $page = $this->get('/account/quotes');

        self::assertSame(200, $this->statusCode());
        $card = $page->filter('[data-testid="quote"]');
        self::assertCount(1, $card);
        self::assertStringContainsString((string) $scenario['quote']->getRef(), $card->text());
        self::assertSame('quoted', $card->filter('[data-testid="quote-state"]')->attr('data-state'));
        self::assertNotSame('', trim($card->filter('[data-testid="quote-valid-until"]')->text()));
        self::assertSame($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]), $card->filter('a')->attr('href'));
    }

    public function testTheTotalOfAQuoteTheShopHasNotAnsweredIsTheAmountAskedFor(): void
    {
        $scenario = $this->requestedQuote();
        $this->signInAs($scenario['buyer']);

        $card = $this->get('/account/quotes')->filter('[data-testid="quote"]');

        self::assertSame('requested', $card->filter('[data-testid="quote-state"]')->attr('data-state'));
        $total = $card->filter('[data-testid="quote-total"]');
        self::assertCount(1, $total);
        self::assertSame('Requested amount', trim($total->filter('[data-testid="quote-total-label"]')->text()));
        self::assertStringContainsString('€1,440.00', $this->flat($total->text()), '10 x 100 and 4 x 50 with taxes: the prices of the cart.');
    }

    public function testTheTotalOfAQuoteTheShopAnsweredIsNotCalledTheAmountAskedFor(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);

        $total = $this->get('/account/quotes')->filter('[data-testid="quote"] [data-testid="quote-total"]');

        self::assertCount(1, $total);
        self::assertStringContainsString('€1,620.00', $this->flat($total->text()));
        self::assertCount(0, $total->filter('[data-testid="quote-total-label"]'));
        self::assertStringNotContainsString('Requested amount', $total->text());
    }

    public function testAQuotePastItsLastDayReadsAsExpiredInTheList(): void
    {
        $scenario = $this->quotedQuote();
        $this->expire($scenario['quote']);
        $this->signInAs($scenario['buyer']);

        $card = $this->get('/account/quotes')->filter('[data-testid="quote"]');

        self::assertSame('expired', $card->filter('[data-testid="quote-state"]')->attr('data-state'));
    }

    public function testAnAccountWithoutQuotesIsToldSoAndSentToItsCart(): void
    {
        $this->signInAs($this->customer());

        $page = $this->get('/account/quotes');

        self::assertCount(1, $page->filter('[data-testid="quotes-empty"]'));
        self::assertCount(0, $page->filter('[data-testid="quote"]'));
    }

    public function testTheListIsPagedByTheApiNewestFirst(): void
    {
        $buyer = $this->customer();
        $product = $this->product('10', 'Product S');
        $refs = [];

        for ($count = 0; $count < 12; ++$count) {
            $refs[] = (string) $this->simpleQuote($buyer, $product)->getRef();
        }

        $this->signInAs($buyer);
        $first = $this->get('/account/quotes');
        $second = $this->get('/account/quotes?page=2');

        self::assertSame(array_reverse(\array_slice($refs, 2)), $this->refsOf($first), 'Ten, the newest first.');
        self::assertSame(array_reverse(\array_slice($refs, 0, 2)), $this->refsOf($second));
        self::assertCount(1, $first->filter('nav[aria-label], .Pagination, [class*="Pagination"]')->slice(0, 1));
    }

    public function testAPageNumberThatIsNotOneIsTheFirstPage(): void
    {
        $buyer = $this->customer();
        $quote = $this->simpleQuote($buyer);
        $this->signInAs($buyer);

        foreach (['abc', '-4', '0'] as $page) {
            $list = $this->get('/account/quotes?page='.$page);

            self::assertSame(200, $this->statusCode(), $page);
            self::assertSame([(string) $quote->getRef()], $this->refsOf($list), $page);
        }
    }

    private function flat(string $text): string
    {
        return trim(preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $text) ?? '');
    }

    /**
     * @return list<string>
     */
    private function refsOf(Crawler $page): array
    {
        return $page->filter('[data-testid="quote"]')->each(static function (Crawler $card): string {
            preg_match('/QUO\d+/', $card->text(), $matches);

            return $matches[0] ?? '';
        });
    }
}
