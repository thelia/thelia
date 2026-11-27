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

use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\Customer;
use Thelia\Model\Quote;

/**
 * Declining a quote, as the button of its page does: a POST that the buyer confirms, that names the revision and
 * carries the token of the account, and that brings them back to the page of the quote.
 */
final class QuoteRejectionTest extends QuoteFlexyTestCase
{
    public function testDecliningIsRecordedAndTheBuyerComesBackToTheQuote(): void
    {
        $scenario = $this->quotedQuote();

        $this->reject($scenario);

        self::assertResponseRedirects($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]).'?rejected=1');
        self::assertSame('rejected', $this->reread($scenario['quote'])->getState());

        $page = $this->client->followRedirect();
        self::assertCount(1, $page->filter('[data-testid="quote-rejected"]'));
        self::assertSame('rejected', $page->filter('[data-testid="quote-state"]')->attr('data-state'));
        self::assertCount(0, $page->filter('[data-testid="quote-reply"]'), 'A declined quote offers nothing.');
    }

    public function testDecliningNeedsTheConfirmation(): void
    {
        $scenario = $this->quotedQuote();

        foreach ([[], ['confirm' => ''], ['confirm' => '0'], ['confirm' => 'yes']] as $fields) {
            $this->reject($scenario, 2, $fields);

            self::assertSame(400, $this->statusCode(), json_encode($fields));
            self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
        }
    }

    public function testWithoutTheTokenOfTheAccountNothingIsDeclined(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);

        foreach ([null, '', 'forged'] as $token) {
            $this->client->request('POST', $this->url('account_quote_reject', ['quoteId' => $scenario['quote']->getId()]), (null === $token ? [] : ['_token' => $token]) + ['revision' => '2', 'confirm' => '1']);

            self::assertSame(403, $this->statusCode(), (string) $token);
        }

        self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
    }

    public function testARevisionTheQuoteHasMovedPastIsRefusedAndTheBuyerIsToldOnThePage(): void
    {
        $scenario = $this->quotedQuote();

        $this->reject($scenario, 1);

        self::assertResponseRedirects($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));
        self::assertStringContainsString('more recent revision', $this->client->followRedirect()->filter('[data-testid="quote-answer-error"]')->text());
        self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
    }

    public function testAQuoteThatIsNotWaitingForTheBuyerCannotBeDeclined(): void
    {
        $expired = $this->quotedQuote();
        $this->expire($expired['quote']);
        $accepted = $this->quotedQuote();
        $this->quotes->accept($accepted['quote'], $accepted['buyer'], 2);

        foreach (['expired' => [$expired, 'quoted'], 'accepted' => [$accepted, 'accepted']] as $state => [$scenario, $stored]) {
            $this->reject($scenario);

            self::assertResponseRedirects($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]), null, $state);
            self::assertStringContainsString('not in a state that allows this', $this->client->followRedirect()->filter('[data-testid="quote-answer-error"]')->text(), $state);
            self::assertSame($stored, $this->reread($scenario['quote'])->getState(), $state);
        }
    }

    public function testACustomerCannotDeclineTheQuoteOfAnotherInEitherDirection(): void
    {
        $first = $this->quotedQuote();
        $second = $this->quotedQuote();

        $this->rejectAs($first['buyer'], $second['quote']);
        self::assertSame(404, $this->statusCode(), 'A declines the quote of B');
        $this->rejectAs($second['buyer'], $first['quote']);
        self::assertSame(404, $this->statusCode(), 'B declines the quote of A');

        self::assertSame('quoted', $this->reread($first['quote'])->getState());
        self::assertSame('quoted', $this->reread($second['quote'])->getState());
    }

    public function testACompanyCannotDeclineTheQuoteOfAnotherCompanyInEitherDirection(): void
    {
        $firstCompany = $this->company('Acme');
        $firstAdministrator = $this->buyer($firstCompany, CompanyRoleCode::ADMINISTRATOR);
        $first = $this->quotedQuote($this->buyer($firstCompany));
        $secondCompany = $this->company('Globex');
        $secondAdministrator = $this->buyer($secondCompany, CompanyRoleCode::ADMINISTRATOR);
        $second = $this->quotedQuote($this->buyer($secondCompany));

        $this->rejectAs($firstAdministrator, $second['quote']);
        self::assertSame(404, $this->statusCode(), 'S1 declines the quote of S2');
        $this->rejectAs($secondAdministrator, $first['quote']);
        self::assertSame(404, $this->statusCode(), 'S2 declines the quote of S1');

        self::assertSame('quoted', $this->reread($first['quote'])->getState());
        self::assertSame('quoted', $this->reread($second['quote'])->getState());

        $this->rejectAs($firstAdministrator, $first['quote']);
        self::assertSame(302, $this->statusCode());
        self::assertSame('rejected', $this->reread($first['quote'])->getState());
    }

    public function testAColleagueWhoMayNotAnswerCannotDeclineAndOneWhoMayNotOrderCan(): void
    {
        $company = $this->company('Acme');
        $author = $this->buyer($company);
        $approver = $this->buyer($company, CompanyRoleCode::APPROVER);
        $this->roleOf('reader', [CompanyPermission::ORDER_VIEW_COMPANY, CompanyPermission::QUOTE_REQUEST, CompanyPermission::ORDER_VIEW_OWN]);
        $reader = $this->buyer($company, 'reader');
        $stranger = $this->buyer($company);
        $scenario = $this->quotedQuote($author);

        foreach ([['approver', $approver, 403], ['colleague who does not read the quote of the company', $stranger, 404]] as [$who, $customer, $status]) {
            $this->rejectAs($customer, $scenario['quote']);

            self::assertSame($status, $this->statusCode(), $who);
            self::assertSame('quoted', $this->reread($scenario['quote'])->getState(), $who);
        }

        // Declining is the right to answer; placing orders is not needed.
        $this->rejectAs($reader, $scenario['quote']);
        self::assertSame(302, $this->statusCode());
        self::assertSame('rejected', $this->reread($scenario['quote'])->getState());
    }

    public function testAVisitorIsSentToSignIn(): void
    {
        $scenario = $this->quotedQuote();

        $this->client->request('POST', $this->url('account_quote_reject', ['quoteId' => $scenario['quote']->getId()]), ['revision' => '2', 'confirm' => '1']);

        self::assertSame(302, $this->statusCode());
        self::assertStringContainsString($this->url('customer_login'), (string) $this->client->getResponse()->headers->get('Location'));
        self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
    }

    /**
     * @param array{buyer: Customer, quote: Quote} $scenario
     * @param array<string, string>|null           $fields   what the form sends besides the token and the revision; the confirmation by default
     */
    private function reject(array $scenario, int $revision = 2, ?array $fields = null): void
    {
        $token = $this->tokenForTheSession();
        $this->signInAs($scenario['buyer']);
        $this->client->request('POST', $this->url('account_quote_reject', ['quoteId' => $scenario['quote']->getId()]), ['_token' => $token, 'revision' => (string) $revision] + ($fields ?? ['confirm' => '1']));
    }

    private function rejectAs(Customer $customer, Quote $quote): void
    {
        $token = $this->tokenForTheSession();
        $this->signInAs($customer);
        $this->client->request('POST', $this->url('account_quote_reject', ['quoteId' => $quote->getId()]), ['_token' => $token, 'revision' => '2', 'confirm' => '1']);
    }
}
