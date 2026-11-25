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

/**
 * A shop that only sells through quotes places no ordinary cart: the buyer who reaches a step past the cart with one, by
 * its address, is sent back to the cart with the sentence of the core instead of walking the steps until the order is
 * refused. The cart of a quote goes through.
 */
final class QuoteOnlyCheckoutTest extends QuoteFlexyTestCase
{
    private const SENTENCE = 'This shop only sells through quotes: ask for a quote for this cart.';

    /**
     * @return iterable<string, array{string}>
     */
    public static function stepsPastTheCart(): iterable
    {
        yield 'delivery' => ['/checkout/delivery'];
        yield 'payment' => ['/checkout/payment'];
        yield 'a step of a module' => ['/checkout/step/some_module_step'];
    }

    #[DataProvider('stepsPastTheCart')]
    public function testAnOrdinaryCartIsSentBackToTheCartWithTheSentenceOfTheCore(string $path): void
    {
        $this->setQuoteMode('only');
        $buyer = $this->customer();
        $this->signInWithCart($buyer, $this->cartOf($buyer, [[$this->product('10', 'Own product'), 1.0, '10']]));

        $this->get($path);

        self::assertResponseRedirects('/checkout/cart');
        $page = $this->client->followRedirect();
        self::assertStringContainsString(self::SENTENCE, $page->filter('.SnackBar--error')->text());
        self::assertCount(0, $page->filter('[data-testid="quote-cart-exit"]'));
    }

    #[DataProvider('stepsPastTheCart')]
    public function testTheSentenceIsNotSaidWhenTheShopSellsOtherwise(string $path): void
    {
        foreach (['alongside', 'disabled'] as $mode) {
            $this->setQuoteMode($mode);
            $buyer = $this->customer();
            $this->signInWithCart($buyer, $this->cartOf($buyer, [[$this->product('10', 'Own product'), 1.0, '10']]));

            $this->get($path);
            $location = (string) $this->client->getResponse()->headers->get('Location');

            // Whatever the tunnel does with this cart (it may send the buyer to the step it has something to do at), it does not say that.
            $page = $this->client->getResponse()->isRedirect() ? $this->client->followRedirect() : $this->client->getCrawler();
            self::assertStringNotContainsString('only sells through quotes', $page->filter('body')->text(), $mode.' '.$location);
        }
    }

    public function testTheCartOfAQuoteGoesThroughInThisMode(): void
    {
        $scenario = $this->quotedQuoteReadyToPay();
        $token = $this->tokenForTheSession();
        $this->signInAs($scenario['buyer']);
        $this->client->request('POST', $this->url('account_quote_accept', ['quoteId' => $scenario['quote']->getId()]), ['_token' => $token, 'revision' => '2']);
        self::assertResponseRedirects('/checkout/payment');
        $this->setQuoteMode('only');

        foreach (['/checkout/delivery', '/checkout/payment'] as $path) {
            $page = $this->get($path);

            if ($this->client->getResponse()->isRedirect()) {
                self::assertStringNotContainsString('/checkout/cart', (string) $this->client->getResponse()->headers->get('Location'), $path.': not sent back to the cart for being no quote.');
                $page = $this->client->followRedirect();
            }

            self::assertSame(200, $this->statusCode(), $path);
            self::assertStringNotContainsString('only sells through quotes', $page->filter('body')->text(), $path);
        }

        $payment = $this->get('/checkout/payment');
        self::assertSame(200, $this->statusCode());
        self::assertMatchesRegularExpression('/Total\s*€1,620\.00/', preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $payment->filter('.Summary')->first()->text()) ?? '');
    }

    public function testAnEmptyCartIsNotToldToAskForAQuote(): void
    {
        $this->setQuoteMode('only');
        $this->signInAs($this->customer());

        $this->get('/checkout/payment');
        $page = $this->client->getResponse()->isRedirect() ? $this->client->followRedirect() : $this->client->getCrawler();

        self::assertStringNotContainsString('only sells through quotes', $page->filter('body')->text());
    }
}
