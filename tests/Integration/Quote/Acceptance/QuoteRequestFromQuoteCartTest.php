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

namespace Thelia\Tests\Integration\Quote\Acceptance;

use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Domain\Quote\Event\QuoteEvents;
use Thelia\Domain\Quote\Exception\QuoteRequestRefusedException;
use Thelia\Model\QuoteQuery;

/**
 * The prices of the cart of a quote are the ones that were negotiated: a request written from it
 * would give them as the catalog prices of a new quote.
 */
final class QuoteRequestFromQuoteCartTest extends QuoteAcceptanceTestCase
{
    public function testTheCartOfAQuoteIsNoCartToAskAQuoteFrom(): void
    {
        $scenario = $this->quotedScenario();
        $cart = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);
        $quotesBefore = QuoteQuery::create()->count();

        try {
            $this->quotes->requestFromCart($cart, $scenario['buyer'], new QuoteRequestInput());
            self::fail('The cart of a quote cannot be turned into a request.');
        } catch (QuoteRequestRefusedException $refusal) {
            self::assertSame(QuoteRequestRefusedException::QUOTE_CART, $refusal->reason());
        }

        self::assertSame($quotesBefore, QuoteQuery::create()->count());
        self::assertCount(1, $this->eventsOf(QuoteEvents::REQUESTED), 'only the request the scenario made');
    }

    public function testTheCartOfAQuoteOfAnotherAccountIsStillTheCartOfAnother(): void
    {
        $scenario = $this->quotedScenario();
        $cart = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);

        try {
            $this->quotes->requestFromCart($cart, $this->customer(), new QuoteRequestInput());
            self::fail('A cart of somebody else is refused.');
        } catch (QuoteRequestRefusedException $refusal) {
            self::assertSame(QuoteRequestRefusedException::CART_NOT_OWNED, $refusal->reason(), 'what the cart is for is not told to an account that does not own it');
        }
    }

    public function testAnOrdinaryCartOfTheSameBuyerIsStillAskedFor(): void
    {
        $scenario = $this->quotedScenario();
        $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);
        $ordinary = $this->cartOf($scenario['buyer'], [[$this->product('100', 'Product D'), 1.0, '100']]);

        $quote = $this->quotes->requestFromCart($ordinary, $scenario['buyer'], new QuoteRequestInput());

        self::assertNotSame($scenario['quote']->getId(), $quote->getId());
    }
}
