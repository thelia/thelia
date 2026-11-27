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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\Company;
use Thelia\Model\Customer;
use Thelia\Model\Quote;

/**
 * A quote is read by its buyer and by the buyers of the company whose role reads the orders of the company,
 * and by no one else: not another customer, not another company, not a colleague without that role, and the
 * page, its history and its document all answer the same 404 as a number that does not exist.
 */
final class QuoteIsolationTest extends QuoteFlexyTestCase
{
    private const PAGES = ['account_quote', 'account_quote_history', 'account_quote_pdf'];

    /**
     * @return iterable<string, array{string}>
     */
    public static function pages(): iterable
    {
        foreach (self::PAGES as $route) {
            yield $route => [$route];
        }
    }

    #[DataProvider('pages')]
    public function testACustomerDoesNotReadTheQuoteOfAnotherCustomerInEitherDirection(string $route): void
    {
        $first = $this->quotedQuote();
        $second = $this->quotedQuote();

        $this->signInAs($first['buyer']);
        $this->get($this->url($route, ['quoteId' => $second['quote']->getId()]));
        self::assertSame(404, $this->statusCode(), 'A reads the quote of B');
        $this->get($this->url($route, ['quoteId' => $first['quote']->getId()]));
        self::assertSame(200, $this->statusCode(), 'A reads their own');

        $this->signInAs($second['buyer']);
        $this->get($this->url($route, ['quoteId' => $first['quote']->getId()]));
        self::assertSame(404, $this->statusCode(), 'B reads the quote of A');
        $this->get($this->url($route, ['quoteId' => $second['quote']->getId()]));
        self::assertSame(200, $this->statusCode(), 'B reads their own');
    }

    #[DataProvider('pages')]
    public function testTheAnswerForAQuoteThatIsNotTheirsIsTheOneOfANumberThatDoesNotExist(string $route): void
    {
        $scenario = $this->quotedQuote();
        $stranger = $this->customer();
        $this->signInAs($stranger);

        $this->get($this->url($route, ['quoteId' => $scenario['quote']->getId()]));
        $foreign = $this->client->getResponse();
        $this->get($this->url($route, ['quoteId' => 99999999]));
        $missing = $this->client->getResponse();

        self::assertSame($missing->getStatusCode(), $foreign->getStatusCode());
        self::assertStringNotContainsString((string) $scenario['quote']->getRef(), (string) $foreign->getContent());
    }

    public function testACustomerDoesNotReadARevisionOfTheQuoteOfAnotherCustomerInEitherDirection(): void
    {
        $first = $this->quotedQuote();
        $second = $this->quotedQuote();
        $revision = static fn (array $scenario): string => '/account/quote/'.$scenario['quote']->getId().'/revision/2';

        $this->signInAs($first['buyer']);
        $this->get($revision($second));
        self::assertSame(404, $this->statusCode(), 'A reads the revision of B');
        $this->get($revision($first));
        self::assertSame(200, $this->statusCode(), 'A reads their own');

        $this->signInAs($second['buyer']);
        $this->get($revision($first));
        self::assertSame(404, $this->statusCode(), 'B reads the revision of A');
        $this->get($revision($second));
        self::assertSame(200, $this->statusCode(), 'B reads their own');
    }

    public function testAListShowsTheQuotesOfTheAccountAndNoOtherInEitherDirection(): void
    {
        $first = $this->quotedQuote();
        $second = $this->quotedQuote();

        $this->signInAs($first['buyer']);
        $page = $this->get('/account/quotes');
        self::assertSame(200, $this->statusCode());
        self::assertSame([(string) $first['quote']->getRef()], $this->refsOf($page));

        $this->signInAs($second['buyer']);
        self::assertSame([(string) $second['quote']->getRef()], $this->refsOf($this->get('/account/quotes')));
    }

    #[DataProvider('pages')]
    public function testACompanyDoesNotReadTheQuotesOfAnotherCompanyInEitherDirection(string $route): void
    {
        [$firstAdmin, $firstQuote] = $this->quoteOfACompany('Acme');
        [$secondAdmin, $secondQuote] = $this->quoteOfACompany('Globex');

        $this->signInAs($firstAdmin);
        $this->get($this->url($route, ['quoteId' => $secondQuote->getId()]));
        self::assertSame(404, $this->statusCode(), 'S1 reads the quote of S2');
        $this->get($this->url($route, ['quoteId' => $firstQuote->getId()]));
        self::assertSame(200, $this->statusCode());

        $this->signInAs($secondAdmin);
        $this->get($this->url($route, ['quoteId' => $firstQuote->getId()]));
        self::assertSame(404, $this->statusCode(), 'S2 reads the quote of S1');
        $this->get($this->url($route, ['quoteId' => $secondQuote->getId()]));
        self::assertSame(200, $this->statusCode());
    }

    public function testTheListOfACompanyHoldsItsQuotesAndNotTheOnesOfAnotherCompany(): void
    {
        [$firstAdmin, $firstQuote] = $this->quoteOfACompany('Acme');
        [$secondAdmin, $secondQuote] = $this->quoteOfACompany('Globex');

        $this->signInAs($firstAdmin);
        self::assertSame([(string) $firstQuote->getRef()], $this->refsOf($this->get('/account/quotes')));

        $this->signInAs($secondAdmin);
        self::assertSame([(string) $secondQuote->getRef()], $this->refsOf($this->get('/account/quotes')));
    }

    public function testAColleagueReadsTheQuoteOfTheCompanyOnlyWithTheRoleThatReadsTheCompanyOrders(): void
    {
        $company = $this->company('Acme');
        $author = $this->buyer($company);
        $colleague = $this->buyer($company);
        $approver = $this->buyer($company, CompanyRoleCode::APPROVER);
        $administrator = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $quote = $this->quotedQuote($author)['quote'];
        self::assertSame($company->getId(), $quote->getCompanyId());

        $expected = [
            'author' => [$author, 200],
            'colleague' => [$colleague, 404],
            'approver' => [$approver, 200],
            'administrator' => [$administrator, 200],
        ];

        foreach ($expected as $who => [$customer, $status]) {
            $this->signInAs($customer);
            $this->get($this->url('account_quote', ['quoteId' => $quote->getId()]));
            self::assertSame($status, $this->statusCode(), $who.' opens the page');
            $this->get($this->url('account_quote_pdf', ['quoteId' => $quote->getId()]));
            self::assertSame($status, $this->statusCode(), $who.' opens the document');
            self::assertSame($status === 200 ? [(string) $quote->getRef()] : [], $this->refsOf($this->get('/account/quotes')), $who.' lists it');
        }
    }

    public function testAVisitorAndAGuestAreNotLetIn(): void
    {
        $scenario = $this->quotedQuote();

        foreach (self::PAGES as $route) {
            $this->get($this->url($route, ['quoteId' => $scenario['quote']->getId()]));
            self::assertSame(302, $this->statusCode(), $route);
            self::assertStringContainsString($this->url('customer_login'), (string) $this->client->getResponse()->headers->get('Location'));
        }

        $this->get('/account/quotes');
        self::assertSame(302, $this->statusCode());
    }

    /**
     * @return array{Customer, Quote} the administrator of a new company and a quote one of its buyers asked for and the shop answered
     */
    private function quoteOfACompany(string $name): array
    {
        $company = $this->company($name);
        $administrator = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $quote = $this->quotedQuote($this->buyer($company))['quote'];

        return [$administrator, $quote];
    }

    /**
     * @return list<string> the references of the quotes a list page shows
     */
    private function refsOf(Crawler $page): array
    {
        return $page->filter('[data-testid="quote"]')->each(static function (Crawler $card): string {
            preg_match('/QUO\d+/', $card->text(), $matches);

            return $matches[0] ?? '';
        });
    }
}
