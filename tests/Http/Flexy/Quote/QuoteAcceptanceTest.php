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
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Customer;
use Thelia\Model\Quote;

/**
 * Accepting a quote, as the button of its page does: a POST that names the quote and the revision and carries the
 * token of the account. What the buyer pays is the quote's, whatever else the request says, and the buyer lands on the
 * step of the checkout that still has something to do.
 */
final class QuoteAcceptanceTest extends QuoteFlexyTestCase
{
    public function testAcceptingLeadsToTheDeliveryWhenTheQuoteCarriesNoAddressNorCarrier(): void
    {
        $scenario = $this->quotedQuote();

        $this->accept($scenario);

        self::assertResponseRedirects('/checkout/delivery');
        self::assertSame('accepted', $this->reread($scenario['quote'])->getState());
        $cart = $this->theCartOf($scenario['quote']);
        self::assertSame((int) $scenario['buyer']->getId(), $cart->getCustomerId());
        self::assertSame(2, (int) $cart->getQuoteRevision());

        // The cart of the session is the one of the quote: the buyer reads its lines, not the cart they had.
        $page = $this->get('/checkout/cart');
        self::assertSame(200, $this->statusCode());
        self::assertStringContainsString('Product C', $page->filter('body')->text(), 'The line the shop added is in the cart in hand.');
    }

    public function testAcceptingLeadsToThePaymentWhenTheQuoteCarriesAnAddressAndACarrier(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();

        $this->accept($scenario);

        self::assertResponseRedirects('/checkout/payment');
    }

    public function testTheCartTheBuyerPaysHasTheLinesOfTheQuoteAtItsPrices(): void
    {
        $scenario = $this->quotedQuote();

        $this->accept($scenario);

        $lines = [];

        foreach (CartItemQuery::create()->filterByCartId($this->theCartOf($scenario['quote'])->getId())->orderById()->find() as $line) {
            $lines[] = [(float) $line->getQuantity(), (string) $line->getPrice(), (string) $line->getPromoPrice()];
        }

        self::assertSame([[12.0, '90.000000', '90.000000'], [4.0, '45.000000', '45.000000'], [5.0, '20.000000', '20.000000']], $lines);
        self::assertSame('30.000000', (string) $this->theCartOf($scenario['quote'])->getDiscount());
    }

    public function testNoAmountOfTheRequestIsTaken(): void
    {
        $scenario = $this->quotedQuote();

        $this->accept($scenario, 2, ['price' => '1', 'total' => '1', 'discount' => '999', 'postage' => '0', 'quantity' => '1', 'catalog_price' => '1']);

        $cart = $this->theCartOf($scenario['quote']);
        $lines = [];

        foreach (CartItemQuery::create()->filterByCartId($cart->getId())->orderById()->find() as $line) {
            $lines[] = [(float) $line->getQuantity(), (string) $line->getPrice()];
        }

        self::assertSame([[12.0, '90.000000'], [4.0, '45.000000'], [5.0, '20.000000']], $lines);
        self::assertSame('30.000000', (string) $cart->getDiscount());
    }

    public function testAcceptingTwiceGivesOneCart(): void
    {
        $scenario = $this->quotedQuote();

        $this->accept($scenario);
        $first = $this->theCartOf($scenario['quote']);
        $this->accept($scenario);

        self::assertResponseRedirects('/checkout/delivery');
        self::assertSame(1, CartQuery::create()->filterByQuoteId($scenario['quote']->getId())->count());
        self::assertSame($first->getId(), $this->theCartOf($scenario['quote'])->getId());
        self::assertSame(3, CartItemQuery::create()->filterByCartId($first->getId())->count());
    }

    public function testARevisionTheQuoteHasMovedPastIsRefusedAndTheBuyerIsToldOnThePage(): void
    {
        $scenario = $this->quotedQuote();

        $this->accept($scenario, 1);

        self::assertResponseRedirects($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));
        $page = $this->client->followRedirect();
        self::assertStringContainsString('more recent revision', $page->filter('[data-testid="quote-answer-error"]')->text());
        self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
        self::assertSame(0, CartQuery::create()->filterByQuoteId($scenario['quote']->getId())->count());
    }

    public function testAnExpiredQuoteRefusesTheAcceptanceAndSaysSo(): void
    {
        $scenario = $this->quotedQuote();
        $this->expire($scenario['quote']);

        $this->accept($scenario);

        self::assertResponseRedirects($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));
        $page = $this->client->followRedirect();
        self::assertStringContainsString('has expired', $page->filter('[data-testid="quote-answer-error"]')->text());
        self::assertSame('quoted', $this->reread($scenario['quote'])->getState(), 'Nothing was written.');
        self::assertSame(0, CartQuery::create()->filterByQuoteId($scenario['quote']->getId())->count());
    }

    public function testAnExtendedQuoteCanBeAcceptedAgain(): void
    {
        $scenario = $this->quotedQuote();
        $this->expire($scenario['quote']);
        $this->quotes->extend($this->reread($scenario['quote']), new \DateTimeImmutable('+10 days'));

        $this->accept($scenario);

        self::assertResponseRedirects('/checkout/delivery');
        self::assertSame('accepted', $this->reread($scenario['quote'])->getState());
    }

    public function testALineThatIsNoLongerSoldRefusesTheAcceptanceAndNamesIt(): void
    {
        $scenario = $this->quotedQuote();
        $this->getPropelConnection()->exec('DELETE FROM `product` WHERE `id` = '.(int) $scenario['b']->getId());

        $this->accept($scenario);

        self::assertResponseRedirects($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));
        $page = $this->client->followRedirect();
        self::assertStringContainsString('no longer sold', $page->filter('[data-testid="quote-answer-error"]')->text());
        $listed = $page->filter('[data-testid="quote-unavailable-lines"] li');
        self::assertCount(1, $listed);
        self::assertStringContainsString('Product B', $listed->text());
        self::assertStringContainsString((string) $scenario['b']->getRef(), $listed->text());
        self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
        self::assertSame(200, $this->statusCode(), 'The quote stays readable.');
    }

    public function testWithoutTheTokenOfTheAccountNothingIsAccepted(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);
        $this->get($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()]));

        foreach ([null, '', 'forged'] as $token) {
            $this->client->request('POST', $this->url('account_quote_accept', ['quoteId' => $scenario['quote']->getId()]), null === $token ? ['revision' => '2'] : ['_token' => $token, 'revision' => '2']);

            self::assertSame(403, $this->statusCode(), (string) $token);
        }

        self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
        self::assertSame(0, CartQuery::create()->filterByQuoteId($scenario['quote']->getId())->count());
    }

    public function testARequestThatNamesNoRevisionIsRefused(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);
        $token = $this->tokenOf($this->get($this->url('account_quote', ['quoteId' => $scenario['quote']->getId()])));

        foreach ([[], ['revision' => ''], ['revision' => 'last'], ['revision' => '0'], ['revision' => '-1']] as $fields) {
            $this->client->request('POST', $this->url('account_quote_accept', ['quoteId' => $scenario['quote']->getId()]), ['_token' => $token] + $fields);

            self::assertSame(400, $this->statusCode(), json_encode($fields));
        }

        self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
    }

    public function testTheAcceptanceIsAPostOnly(): void
    {
        $scenario = $this->quotedQuote();
        $this->signInAs($scenario['buyer']);

        $this->get($this->url('account_quote_accept', ['quoteId' => $scenario['quote']->getId()]));

        self::assertSame(405, $this->statusCode());
        self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
    }

    public function testACustomerCannotAcceptTheQuoteOfAnotherInEitherDirection(): void
    {
        $first = $this->quotedQuote();
        $second = $this->quotedQuote();

        $this->acceptAs($first['buyer'], $second['quote']);
        self::assertSame(404, $this->statusCode(), 'A accepts the quote of B');
        $this->acceptAs($second['buyer'], $first['quote']);
        self::assertSame(404, $this->statusCode(), 'B accepts the quote of A');

        foreach ([$first, $second] as $scenario) {
            self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
            self::assertSame(0, CartQuery::create()->filterByQuoteId($scenario['quote']->getId())->count());
        }
    }

    public function testACompanyCannotAcceptTheQuoteOfAnotherCompanyInEitherDirection(): void
    {
        $firstCompany = $this->company('Acme');
        $firstAdministrator = $this->buyer($firstCompany, CompanyRoleCode::ADMINISTRATOR);
        $first = $this->quotedQuote($this->buyer($firstCompany));
        $secondCompany = $this->company('Globex');
        $secondAdministrator = $this->buyer($secondCompany, CompanyRoleCode::ADMINISTRATOR);
        $second = $this->quotedQuote($this->buyer($secondCompany));

        $this->acceptAs($firstAdministrator, $second['quote']);
        self::assertSame(404, $this->statusCode(), 'S1 accepts the quote of S2');
        $this->acceptAs($secondAdministrator, $first['quote']);
        self::assertSame(404, $this->statusCode(), 'S2 accepts the quote of S1');

        foreach ([$first, $second] as $scenario) {
            self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
            self::assertSame(0, CartQuery::create()->filterByQuoteId($scenario['quote']->getId())->count());
        }

        // The administrator of the company of the quote can: the refusals above are about the company, not about the role.
        $this->acceptAs($firstAdministrator, $first['quote']);
        self::assertSame(302, $this->statusCode());
        self::assertSame('accepted', $this->reread($first['quote'])->getState());
    }

    public function testAColleagueWhoMayNotAnswerOrMayNotOrderCannotAccept(): void
    {
        $company = $this->company('Acme');
        $author = $this->buyer($company);
        $approver = $this->buyer($company, CompanyRoleCode::APPROVER);
        $this->roleOf('reader', [CompanyPermission::ORDER_VIEW_COMPANY, CompanyPermission::QUOTE_REQUEST, CompanyPermission::ORDER_VIEW_OWN]);
        $reader = $this->buyer($company, 'reader');
        $stranger = $this->buyer($company);
        $scenario = $this->quotedQuote($author);

        foreach ([['approver, who reads the quote but may not answer it', $approver, 403], ['reader, who answers but may not place orders', $reader, 403], ['colleague who does not read the quote of the company', $stranger, 404]] as [$who, $customer, $status]) {
            $this->acceptAs($customer, $scenario['quote']);

            self::assertSame($status, $this->statusCode(), $who);
            self::assertSame('quoted', $this->reread($scenario['quote'])->getState(), $who);
            self::assertSame(0, CartQuery::create()->filterByQuoteId($scenario['quote']->getId())->count(), $who);
        }

        $this->acceptAs($author, $scenario['quote']);
        self::assertSame(302, $this->statusCode(), 'The author does.');
    }

    public function testAVisitorIsSentToSignIn(): void
    {
        $scenario = $this->quotedQuote();

        $this->client->request('POST', $this->url('account_quote_accept', ['quoteId' => $scenario['quote']->getId()]), ['revision' => '2']);

        self::assertSame(302, $this->statusCode());
        self::assertStringContainsString($this->url('customer_login'), (string) $this->client->getResponse()->headers->get('Location'));
        self::assertSame('quoted', $this->reread($scenario['quote'])->getState());
    }

    /**
     * @param array{buyer: Customer, quote: Quote} $scenario
     * @param array<string, string>                $extra
     */
    private function accept(array $scenario, int $revision = 2, array $extra = []): void
    {
        $token = $this->tokenForTheSession();
        $this->signInAs($scenario['buyer']);
        $this->client->request('POST', $this->url('account_quote_accept', ['quoteId' => $scenario['quote']->getId()]), ['_token' => $token, 'revision' => (string) $revision] + $extra);
    }

    private function acceptAs(Customer $customer, Quote $quote): void
    {
        $token = $this->tokenForTheSession();
        $this->signInAs($customer);
        $this->client->request('POST', $this->url('account_quote_accept', ['quoteId' => $quote->getId()]), ['_token' => $token, 'revision' => '2']);
    }

    private function theCartOf(Quote $quote): Cart
    {
        \Thelia\Model\Map\CartTableMap::clearInstancePool();
        $carts = CartQuery::create()->filterByQuoteId($quote->getId())->find();
        self::assertCount(1, $carts, 'The quote has one cart.');

        return $carts->getFirst();
    }
}
