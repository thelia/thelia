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
use Thelia\Model\Customer;

/**
 * The cart page offers the request for a quote according to the setting of the shop: nothing when quotes
 * are off, next to the way to the checkout when they come with orders, instead of it when they are the
 * only way. The cart of an accepted quote is paid, never quoted again.
 */
final class QuoteRequestButtonTest extends QuoteFlexyTestCase
{
    private const BUTTON = '[data-testid="quote-request-button"]';

    /** The next-step button of the cart, a live component that links to the step after the cart. */
    private const CHECKOUT = 'a[href="/checkout/delivery"], a[href="/checkout/payment"]';

    public function testNoButtonAndTheTunnelAsItWasWhenQuotesAreOff(): void
    {
        $this->setQuoteMode('disabled');
        $page = $this->cartPageOf($this->customer());

        self::assertCount(0, $page->filter(self::BUTTON));
        self::assertGreaterThan(0, $page->filter(self::CHECKOUT)->count(), 'The way to the checkout is still drawn.');
    }

    public function testTheButtonComesWithTheWayToTheCheckoutWhenQuotesAreAlongsideOrders(): void
    {
        $this->setQuoteMode('alongside');
        $page = $this->cartPageOf($this->customer());

        self::assertCount(1, $page->filter(self::BUTTON));
        self::assertSame($this->url('account_quote_request'), $page->filter(self::BUTTON)->attr('href'));
        self::assertGreaterThan(0, $page->filter(self::CHECKOUT)->count());
    }

    public function testTheButtonStandsAloneWhenQuotesAreTheOnlyWay(): void
    {
        $this->setQuoteMode('only');
        $page = $this->cartPageOf($this->customer());

        self::assertCount(1, $page->filter(self::BUTTON));
        self::assertCount(0, $page->filter(self::CHECKOUT), 'The direct way to the checkout is gone.');
    }

    public function testAnUnknownSettingReadsAsQuotesOff(): void
    {
        $this->setQuoteMode('whenever');
        $page = $this->cartPageOf($this->customer());

        self::assertCount(0, $page->filter(self::BUTTON));
        self::assertGreaterThan(0, $page->filter(self::CHECKOUT)->count());
    }

    #[DataProvider('modesWithQuotes')]
    public function testTheCartOfAnAcceptedQuoteIsNotOfferedAnotherQuoteAndStaysPayable(string $mode): void
    {
        $scenario = $this->quotedQuote();
        $cart = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);
        $this->setQuoteMode($mode);

        $this->signInWithCart($scenario['buyer'], $cart);
        $page = $this->get('/checkout/cart');

        self::assertSame(200, $this->statusCode());
        self::assertCount(0, $page->filter(self::BUTTON));
        self::assertGreaterThan(0, $page->filter(self::CHECKOUT)->count(), 'The cart of a quote is payable in every mode.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function modesWithQuotes(): iterable
    {
        yield 'alongside' => ['alongside'];
        yield 'only' => ['only'];
    }

    public function testABuyerWhoseRoleCannotAskForQuotesIsNotOfferedTheButton(): void
    {
        $approver = $this->buyer($this->company(), CompanyRoleCode::APPROVER);
        $page = $this->cartPageOf($approver);

        self::assertCount(0, $page->filter(self::BUTTON));
    }

    public function testABuyerOfACompanyWhoMayAskIsOfferedTheButton(): void
    {
        $page = $this->cartPageOf($this->buyer($this->company()));

        self::assertCount(1, $page->filter(self::BUTTON));
    }

    public function testTheRouteTheMailsLinkToIsNamedAccountQuoteWithItsNumber(): void
    {
        self::assertSame('/account/quote/42', $this->url('account_quote', ['quoteId' => 42]));
    }

    private function cartPageOf(Customer $customer): Crawler
    {
        $product = $this->product('100', 'Product A');
        $cart = $this->cartOf($customer, [[$product, 2.0, '100']]);
        $this->signInWithCart($customer, $cart);
        $page = $this->get('/checkout/cart');

        self::assertSame(200, $this->statusCode());

        return $page;
    }
}
