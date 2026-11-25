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

/**
 * What the page of a quote offers its buyer to answer with: the three answers on a quote the shop sent and whose
 * validity is not over, none when it waits for the shop or has expired, and only what the role of a buyer of a
 * company allows.
 */
final class QuoteActionsOfferTest extends QuoteFlexyTestCase
{
    private const OFFERED = [
        'accept' => '[data-testid="quote-accept-form"] button[type="submit"]',
        'counter' => '[data-testid="quote-counter-offer-zone"] form[data-testid="quote-counter-offer"]',
        'reject' => '[data-testid="quote-reject-form"] button[type="submit"]',
    ];

    public function testAQuoteTheShopSentOffersTheThreeAnswers(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);

        $page = $this->get($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));

        foreach (self::OFFERED as $answer => $selector) {
            self::assertCount(1, $page->filter($selector), $answer);
        }

        // What the buttons carry is the revision the quote stands at, and no amount.
        self::assertSame('2', $page->filter('[data-testid="quote-accept-form"] input[name="revision"]')->attr('value'));
        self::assertSame('2', $page->filter('[data-testid="quote-reject-form"] input[name="revision"]')->attr('value'));
        self::assertSame([], $page->filter('[data-testid="quote-accept-form"] input')->reduce(static fn ($input): bool => !\in_array($input->attr('name'), ['_token', 'revision'], true))->each(static fn ($input): string => (string) $input->attr('name')));
        self::assertCount(1, $page->filter('[data-testid="quote-reject-form"] button[name="confirm"][value="1"]'), 'The button of the refusal is the confirmation.');
        self::assertSame($this->url('account_quote_accept', ['quoteId' => $scenario['quote']->getId()]), $page->filter('[data-testid="quote-accept-form"]')->attr('action'));
        self::assertSame('post', strtolower((string) $page->filter('[data-testid="quote-accept-form"]')->attr('method')));
    }

    public function testNothingIsOfferedForAQuoteTheShopHasNotAnsweredYet(): void
    {
        $scenario = $this->requestedQuote();
        $this->signInAs($scenario['buyer']);

        $page = $this->get($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));

        self::assertSame(200, $this->statusCode());
        self::assertCount(0, $page->filter('[data-testid="quote-reply"]'));
    }

    public function testNothingIsOfferedForAQuoteThatWaitsForTheShopAndTheBuyerIsToldSo(): void
    {
        $scenario = $this->quotedQuote();
        $this->quotes->counterOffer($scenario['quote'], $scenario['buyer'], 2, [new \Thelia\Domain\Quote\DTO\QuoteItemRequestInput($this->lineIdsOf($scenario['quote'], 2)[(string) $scenario['a']->getRef()], null, '85')]);
        $this->signInAs($scenario['buyer']);

        $page = $this->get($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));

        self::assertSame('countered', $page->filter('[data-testid="quote-state"]')->attr('data-state'));
        self::assertCount(0, $page->filter('[data-testid="quote-reply"]'));
        self::assertCount(1, $page->filter('[data-testid="quote-waiting-for-the-shop"]'));
    }

    public function testNothingIsOfferedForAQuoteWhoseValidityIsOver(): void
    {
        $scenario = $this->quotedQuote();
        $this->expire($scenario['quote']);
        $this->signInAs($scenario['buyer']);

        $page = $this->get($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));

        self::assertSame('expired', $page->filter('[data-testid="quote-state"]')->attr('data-state'));
        self::assertCount(0, $page->filter('[data-testid="quote-reply"]'));
        self::assertCount(0, $page->filter('[data-testid="quote-actions"] form'));
    }

    public function testNoAnswerIsOfferedOnceTheQuoteWasAcceptedOrDeclined(): void
    {
        foreach (['accepted' => fn (array $s) => $this->quotes->accept($s['quote'], $s['buyer'], 2), 'rejected' => fn (array $s) => $this->quotes->reject($s['quote'], $s['buyer'], 2)] as $state => $answer) {
            $scenario = $this->quotedQuote();
            $answer($scenario);
            $this->signInAs($scenario['buyer']);

            $page = $this->get($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));

            self::assertSame($state, $page->filter('[data-testid="quote-state"]')->attr('data-state'));
            self::assertCount(0, $page->filter(implode(', ', self::OFFERED)), $state);
            self::assertCount(0, $page->filter('[data-testid="quote-counter-offer-zone"], [data-testid="quote-reject-zone"]'), $state);
        }
    }

    public function testADeclinedQuoteOffersNothingAtAll(): void
    {
        $scenario = $this->quotedQuote();
        $this->quotes->reject($scenario['quote'], $scenario['buyer'], 2);
        $this->signInAs($scenario['buyer']);

        $page = $this->get($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));

        self::assertCount(0, $page->filter('[data-testid="quote-reply"]'));
    }

    public function testABuyerOfACompanyIsOfferedWhatHisRoleAllows(): void
    {
        $company = $this->company('Acme');
        $author = $this->buyer($company);
        $administrator = $this->buyer($company, CompanyRoleCode::ADMINISTRATOR);
        $approver = $this->buyer($company, CompanyRoleCode::APPROVER);
        $this->roleOf('reader', [CompanyPermission::ORDER_VIEW_COMPANY, CompanyPermission::QUOTE_REQUEST, CompanyPermission::ORDER_VIEW_OWN]);
        $reader = $this->buyer($company, 'reader');
        $quote = $this->quotedQuote($author)['quote'];

        $expected = [
            'author' => [$author, ['accept', 'counter', 'reject']],
            'administrator' => [$administrator, ['accept', 'counter', 'reject']],
            'approver, who reads the quotes of the company but may not answer them' => [$approver, []],
            'reader, who answers but may not place orders' => [$reader, ['counter', 'reject']],
        ];

        foreach ($expected as $who => [$customer, $offered]) {
            $this->signInAs($customer);
            $page = $this->get($this->url('account_quote', ['quoteId' => $quote->getId()]));

            self::assertSame(200, $this->statusCode(), $who);

            foreach (self::OFFERED as $answer => $selector) {
                self::assertCount(\in_array($answer, $offered, true) ? 1 : 0, $page->filter($selector), $who.' / '.$answer);
            }
        }
    }
}
