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

use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteQuery;

/**
 * What an account may write on the quotes in an hour: a handful of requests and of counter-proposals,
 * counted per account, and past it a 429 with the delay to wait, before the facade is called.
 */
final class QuoteRateLimitApiTest extends QuoteApiTestCase
{
    private const int LIMIT = 2;

    protected function setUp(): void
    {
        $this->setLimit('THELIA_API_RATE_LIMIT_QUOTE_REQUEST', (string) self::LIMIT);
        $this->setLimit('THELIA_API_RATE_LIMIT_QUOTE_COUNTER_OFFER', (string) self::LIMIT);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->restoreLimits();

        parent::tearDown();
    }

    public function testTheRequestAfterTheLastAllowedOneIsRefusedWithADelay(): void
    {
        $buyer = $this->customer();
        $token = $this->token($buyer);
        $cart = $this->cartOf($buyer, [[$this->product('100', 'A'), 1.0, '100']]);

        for ($request = 1; $request <= self::LIMIT; ++$request) {
            self::assertSame(201, $this->jsonRequest('POST', '/api/front/account/quotes', ['cartId' => $cart->getId()], $token, 'json')->getStatusCode(), 'Request '.$request);
        }

        $refused = $this->jsonRequest('POST', '/api/front/account/quotes', ['cartId' => $cart->getId()], $token, 'json');

        self::assertSame(429, $refused->getStatusCode());
        self::assertGreaterThan(0, (int) $refused->headers->get('Retry-After'));
        self::assertSame(self::LIMIT, QuoteQuery::create()->count(), 'The refused request wrote nothing.');
    }

    public function testTheBudgetIsThatOfTheAccountAndAnotherAccountKeepsItsOwn(): void
    {
        $first = $this->customer();
        $second = $this->customer();
        $firstCart = $this->cartOf($first, [[$this->product('100', 'A'), 1.0, '100']]);
        $secondCart = $this->cartOf($second, [[$this->product('100', 'B'), 1.0, '100']]);

        for ($request = 1; $request <= self::LIMIT; ++$request) {
            $this->jsonRequest('POST', '/api/front/account/quotes', ['cartId' => $firstCart->getId()], $this->token($first), 'json');
        }

        self::assertSame(429, $this->jsonRequest('POST', '/api/front/account/quotes', ['cartId' => $firstCart->getId()], $this->token($first), 'json')->getStatusCode());
        self::assertSame(201, $this->jsonRequest('POST', '/api/front/account/quotes', ['cartId' => $secondCart->getId()], $this->token($second), 'json')->getStatusCode());
    }

    public function testTheCounterProposalsHaveABudgetOfTheirOwn(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        $token = $this->token($scenario['buyer']);
        $line = (int) QuoteItemQuery::create()->useQuoteRevisionQuery()->filterByQuoteId($quote->getId())->filterByNumber(2)->endUse()->orderByPosition()->findOne()->getId();
        $body = ['revision' => 2, 'lines' => [['quoteItemId' => $line, 'price' => '80']]];

        // The first goes through; the next ones are refused by the state of the quote, and cost as much.
        self::assertSame(200, $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/counter-offer', $body, $token, 'json')->getStatusCode());
        self::assertSame(409, $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/counter-offer', $body, $token, 'json')->getStatusCode());

        $refused = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/counter-offer', $body, $token, 'json');

        self::assertSame(429, $refused->getStatusCode());
        self::assertGreaterThan(0, (int) $refused->headers->get('Retry-After'));

        // Asking for a quote is another budget.
        $cart = $this->cartOf($scenario['buyer'], [[$this->product('100', 'C'), 1.0, '100']]);
        self::assertSame(201, $this->jsonRequest('POST', '/api/front/account/quotes', ['cartId' => $cart->getId()], $token, 'json')->getStatusCode());
    }

    public function testAnAnswerThatCostsNothingIsNotCounted(): void
    {
        $scenario = $this->quotedQuote();
        $token = $this->token($scenario['buyer']);
        $quote = $scenario['quote'];

        for ($read = 0; $read < self::LIMIT * 3; ++$read) {
            self::assertSame(200, $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId(), token: $token)->getStatusCode());
        }

        self::assertSame(200, $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/reject', ['revision' => 2], $token, 'json')->getStatusCode());
    }
}
