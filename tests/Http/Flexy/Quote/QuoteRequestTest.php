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

use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Customer;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteQuery;

/**
 * The form that turns the cart in hand into a request for a quote, as a browser sends it: the comment, the
 * day the goods are needed by and the addresses of the book of the buyer. The cart is left as it is.
 */
final class QuoteRequestTest extends QuoteFlexyTestCase
{
    private Customer $buyer;

    private Cart $cart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = $this->customer();
        $this->cart = $this->cartOf($this->buyer, [
            [$this->product('100', 'Product A'), 10.0, '100'],
            [$this->product('50', 'Product B'), 4.0, '50'],
        ]);
    }

    public function testTheFormOffersTheAddressesOfTheBookOfTheBuyer(): void
    {
        $address = $this->factory->address($this->buyer, null, null, ['label' => 'Head office']);
        $this->signInWithCart($this->buyer, $this->cart);

        $page = $this->get('/account/quote/request');

        self::assertSame(200, $this->statusCode());
        self::assertCount(1, $page->filter('form[action="/account/quote/request"] textarea[name="comment"]'));
        self::assertCount(1, $page->filter('input[type="date"][name="needed_by"]'));
        self::assertCount(1, $page->filter('select[name="delivery_address_id"] option[value="'.$address->getId().'"]'));
        self::assertCount(1, $page->filter('select[name="invoice_address_id"] option[value="'.$address->getId().'"]'));
        self::assertNotSame('', $this->tokenOf($page));
    }

    public function testARequestIsCreatedWithTheCommentTheDayAndTheAddressesAndTheCartIsLeftAsItWas(): void
    {
        $delivery = $this->factory->address($this->buyer, null, null, ['label' => 'Warehouse']);
        $invoice = $this->factory->address($this->buyer, null, null, ['label' => 'Head office']);
        $before = $this->cartLinesOf($this->cart);
        $this->signInWithCart($this->buyer, $this->cart);

        $page = $this->get('/account/quote/request');
        $neededBy = (new \DateTimeImmutable('+20 days'))->format('Y-m-d');
        $this->client->request('POST', '/account/quote/request', [
            '_token' => $this->tokenOf($page),
            'comment' => 'Delivery before the opening, please.',
            'needed_by' => $neededBy,
            'delivery_address_id' => (string) $delivery->getId(),
            'invoice_address_id' => (string) $invoice->getId(),
        ]);

        $quote = $this->theOnlyQuoteOf($this->buyer);
        self::assertResponseRedirects($this->url('account_quote', ['quoteId' => $quote->getId()]).'?created=1');
        self::assertSame('requested', $quote->getState());
        self::assertSame('Delivery before the opening, please.', $quote->getBuyerComment());
        self::assertSame($neededBy, $quote->getNeededBy()?->format('Y-m-d'));
        self::assertSame($delivery->getId(), $quote->getDeliveryAddressId());
        self::assertSame($invoice->getId(), $quote->getInvoiceAddressId());
        self::assertSame(2, QuoteItemQuery::create()->useQuoteRevisionQuery()->filterByQuoteId($quote->getId())->endUse()->count());
        self::assertSame($before, $this->cartLinesOf($this->cart), 'The cart stays as it was.');

        $created = $this->client->followRedirect();
        self::assertSame(200, $this->statusCode());
        self::assertCount(1, $created->filter('[data-testid="quote-created"]'));
        self::assertCount(0, $created->filter('[data-testid="quote-coupon-dropped"]'));
    }

    public function testTheBuyerIsToldWhenThePromoCodeOfTheCartDoesNotComeAlong(): void
    {
        $this->cart->setDiscount('10.000000')->save($this->getPropelConnection());
        $this->signInWithCart($this->buyer, $this->cart);

        $page = $this->get('/account/quote/request');
        $this->client->request('POST', '/account/quote/request', ['_token' => $this->tokenOf($page)]);
        $quote = $this->theOnlyQuoteOf($this->buyer);

        self::assertSame(1, (int) $quote->getCouponDropped());
        $opened = $this->client->followRedirect();
        self::assertCount(1, $opened->filter('[data-testid="quote-coupon-dropped"]'));
    }

    public function testTheFormTellsBeforeTheRequestIsSentThatThePromoCodeOfTheCartWillNotComeAlong(): void
    {
        $this->signInWithCart($this->buyer, $this->cart);
        $without = $this->get('/account/quote/request');
        self::assertCount(0, $without->filter('[data-testid="quote-request-coupon-dropped"]'), 'A cart with no code says nothing of it.');

        $this->cart->setDiscount('10.000000')->save($this->getPropelConnection());
        $this->signInWithCart($this->buyer, $this->cart);
        $with = $this->get('/account/quote/request');

        self::assertSame(200, $this->statusCode());
        self::assertStringContainsString('will not be part of the quote', $with->filter('[data-testid="quote-request-coupon-dropped"]')->text());
        self::assertCount(1, $with->filter('form[action="/account/quote/request"]'), 'The request can still be sent.');
    }

    public function testNothingIsCreatedWithoutTheTokenOfTheSession(): void
    {
        $this->signInWithCart($this->buyer, $this->cart);

        foreach (['', 'forged'] as $token) {
            $this->client->request('POST', '/account/quote/request', ['_token' => $token, 'comment' => 'Hijacked']);

            self::assertSame(403, $this->statusCode(), $token);
        }

        self::assertSame(0, QuoteQuery::create()->filterByCustomerId($this->buyer->getId())->count());
    }

    public function testAVisitorWhoHasNotSignedInIsSentToSignInAndComesBackToTheForm(): void
    {
        $this->get('/account/quote/request');

        self::assertSame(302, $this->statusCode());
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertSame($this->url('customer_login').'?redirect=/account/quote/request', $location);

        $this->client->request('POST', '/account/quote/request', ['comment' => 'x']);
        self::assertSame(302, $this->statusCode());
    }

    public function testThePagesAreNotThereWhenQuotesAreOff(): void
    {
        $this->setQuoteMode('disabled');
        $this->signInWithCart($this->buyer, $this->cart);

        $this->get('/account/quote/request');
        self::assertSame(404, $this->statusCode());

        $this->client->request('POST', '/account/quote/request', ['_token' => 'x']);
        self::assertSame(404, $this->statusCode());
        self::assertSame(0, QuoteQuery::create()->filterByCustomerId($this->buyer->getId())->count());
    }

    public function testARequestForAnEmptyCartSendsTheBuyerBackToTheCart(): void
    {
        $empty = $this->factory->cart($this->buyer);
        $this->signInWithCart($this->buyer, $empty);

        $page = $this->get('/account/quote/request');
        $this->client->request('POST', '/account/quote/request', ['_token' => $this->tokenOf($page)]);

        self::assertResponseRedirects($this->url('checkout_cart'));
        self::assertSame(0, QuoteQuery::create()->filterByCustomerId($this->buyer->getId())->count());
        $cartPage = $this->client->followRedirect();
        self::assertStringContainsString('A quote cannot be asked for an empty cart.', $cartPage->filter('body')->text());
    }

    public function testADayInThePastIsRefusedWithTheReasonAndTheFormComesBack(): void
    {
        $this->signInWithCart($this->buyer, $this->cart);

        $page = $this->get('/account/quote/request');
        $this->client->request('POST', '/account/quote/request', [
            '_token' => $this->tokenOf($page),
            'needed_by' => (new \DateTimeImmutable('-3 days'))->format('Y-m-d'),
        ]);

        self::assertResponseRedirects($this->url('account_quote_request'));
        $again = $this->client->followRedirect();
        self::assertStringContainsString('The day the goods are needed by cannot be in the past.', $again->filter('[data-testid="quote-request-error"]')->text());
        self::assertSame(0, QuoteQuery::create()->filterByCustomerId($this->buyer->getId())->count());
    }

    public function testADayThatIsNotADayIsRefusedBeforeTheCoreIsAsked(): void
    {
        $this->signInWithCart($this->buyer, $this->cart);

        $page = $this->get('/account/quote/request');
        $this->client->request('POST', '/account/quote/request', ['_token' => $this->tokenOf($page), 'needed_by' => '2026-02-31']);

        self::assertResponseRedirects($this->url('account_quote_request'));
        self::assertStringContainsString('Please type a valid date', $this->client->followRedirect()->filter('[data-testid="quote-request-error"]')->text());
        self::assertSame(0, QuoteQuery::create()->filterByCustomerId($this->buyer->getId())->count());
    }

    public function testAnAddressOfSomeoneElseIsRefused(): void
    {
        $stranger = $this->customer();
        $address = $this->factory->address($stranger, null, null, ['label' => 'Not mine']);
        $this->signInWithCart($this->buyer, $this->cart);

        $page = $this->get('/account/quote/request');
        $this->client->request('POST', '/account/quote/request', [
            '_token' => $this->tokenOf($page),
            'delivery_address_id' => (string) $address->getId(),
        ]);

        self::assertResponseRedirects($this->url('account_quote_request'));
        self::assertStringContainsString('You may not use this address on a quote.', $this->client->followRedirect()->filter('[data-testid="quote-request-error"]')->text());
        self::assertSame(0, QuoteQuery::create()->filterByCustomerId($this->buyer->getId())->count());
    }

    public function testABuyerWhoseRoleCannotAskForQuotesGetsNeitherTheFormNorTheRequest(): void
    {
        $approver = $this->buyer($this->company(), CompanyRoleCode::APPROVER);
        $this->signInWithCart($approver, $this->cartOf($approver, [[$this->product('100', 'Product A'), 1.0, '100']]));

        $this->get('/account/quote/request');
        self::assertSame(403, $this->statusCode());

        $this->client->request('POST', '/account/quote/request', ['_token' => 'x']);
        self::assertSame(403, $this->statusCode());
        self::assertSame(0, QuoteQuery::create()->filterByCustomerId($approver->getId())->count());
    }

    public function testTheCartOfAnAcceptedQuoteIsNotQuotedAgain(): void
    {
        $scenario = $this->quotedQuote();
        $cart = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);
        $this->signInWithCart($scenario['buyer'], $cart);

        $this->get('/account/quote/request');

        self::assertResponseRedirects($this->url('checkout_cart'));
        self::assertSame(1, QuoteQuery::create()->filterByCustomerId($scenario['buyer']->getId())->count());
    }

    public function testTheRequestsOfAnAccountAreLimited(): void
    {
        $this->signInWithCart($this->buyer, $this->cart);
        $page = $this->get('/account/quote/request');
        $token = $this->tokenOf($page);
        $statuses = [];

        for ($attempt = 0; $attempt < 14; ++$attempt) {
            $this->client->request('POST', '/account/quote/request', ['_token' => $token]);
            $statuses[] = $this->statusCode();
        }

        self::assertContains(429, $statuses, 'An account that keeps asking is slowed down.');
        self::assertSame(302, $statuses[0]);
        self::assertLessThanOrEqual(10, QuoteQuery::create()->filterByCustomerId($this->buyer->getId())->count());
    }

    private function theOnlyQuoteOf(Customer $customer): Quote
    {
        $quotes = QuoteQuery::create()->filterByCustomerId($customer->getId())->find();
        self::assertCount(1, $quotes);

        return $quotes->getFirst();
    }

    /**
     * @return list<array{float, string}> the lines of the cart: quantity and price
     */
    private function cartLinesOf(Cart $cart): array
    {
        $lines = [];

        foreach (CartItemQuery::create()->filterByCartId($cart->getId())->orderById()->find() as $line) {
            $lines[] = [(float) $line->getQuantity(), (string) $line->getPrice()];
        }

        return $lines;
    }
}
