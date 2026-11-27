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

namespace Thelia\Tests\Integration\Quote;

use Thelia\Domain\Quote\DTO\QuoteItemRequestInput;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Event\QuoteEvents;
use Thelia\Domain\Quote\Event\QuoteRejectedEvent;
use Thelia\Domain\Quote\Exception\CounterOfferRefusedException;
use Thelia\Domain\Quote\Exception\QuoteActionRefusedException;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;
use Thelia\Domain\Quote\Exception\UnknownQuoteException;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemRequestQuery;

/**
 * What a buyer can do with the revision the shop sent: counter it line by line, turn it down, and
 * nothing else. Who may do it is the other half: no one but the account of the quote and the
 * buyers of its company who may answer quotes.
 */
final class QuoteAnswerTest extends QuoteDomainTestCase
{
    /**
     * @return array{buyer: Customer, quote: Quote, a: Product, b: Product, c: Product}
     */
    private function sentScenario(?Customer $buyer = null): array
    {
        $scenario = $this->requestedScenario();

        if (null !== $buyer) {
            // The quote of the scenario belongs to its own buyer: this one asks for a quote of their own.
            $cart = $this->cartOf($buyer, [[$scenario['a'], 10.0, '100'], [$scenario['b'], 4.0, '50']]);
            $scenario['quote'] = $this->quotes->requestFromCart($cart, $buyer, new QuoteRequestInput());
            $scenario['buyer'] = $buyer;
        }

        $this->quotes->saveDraft($scenario['quote'], null, $this->draftOfTheShop($scenario));
        $this->quotes->send($scenario['quote'], null);

        return $scenario;
    }

    private function lineIdOf(Quote $quote, string $productRef, int $revision = 2): int
    {
        return (int) $this->lineOf($this->revisionOf($quote, $revision), $productRef)->getId();
    }

    public function testACounterProposalMayChangeTheQuantityTheUnitPriceOrBoth(): void
    {
        $scenario = $this->sentScenario();
        $quote = $scenario['quote'];

        $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [
            new QuoteItemRequestInput($this->lineIdOf($quote, $scenario['a']->getRef()), 20.0, null),
            new QuoteItemRequestInput($this->lineIdOf($quote, $scenario['b']->getRef()), null, '40.5'),
            new QuoteItemRequestInput($this->lineIdOf($quote, $scenario['c']->getRef()), 8.0, '18'),
        ]);

        $asked = [];

        foreach (QuoteItemRequestQuery::create()->orderByQuoteItemId()->filterByQuoteItemId([
            $this->lineIdOf($quote, $scenario['a']->getRef()),
            $this->lineIdOf($quote, $scenario['b']->getRef()),
            $this->lineIdOf($quote, $scenario['c']->getRef()),
        ])->find() as $request) {
            $asked[] = [$request->getQuantity(), $request->getPrice()];
        }

        self::assertSame([[20.0, null], [null, '40.500000'], [8.0, '18.000000']], $asked);
    }

    public function testACounterProposalOnlyDiscussesTheLinesOfTheRevisionItAnswers(): void
    {
        $scenario = $this->sentScenario();
        $quote = $scenario['quote'];
        $lineOfTheRequest = $this->lineIdOf($quote, $scenario['a']->getRef(), 1);
        $lineOfA = $this->lineIdOf($quote, $scenario['a']->getRef());
        $other = $this->sentScenario();
        $lineOfAnotherQuote = $this->lineIdOf($other['quote'], $other['a']->getRef());

        foreach ([
            CounterOfferRefusedException::UNKNOWN_LINE.' (line of the first revision)' => [new QuoteItemRequestInput($lineOfTheRequest, 1.0, null)],
            CounterOfferRefusedException::UNKNOWN_LINE.' (line of another quote)' => [new QuoteItemRequestInput($lineOfAnotherQuote, 1.0, null)],
            CounterOfferRefusedException::UNKNOWN_LINE.' (line that does not exist)' => [new QuoteItemRequestInput(999999999, 1.0, null)],
            CounterOfferRefusedException::DUPLICATE_LINE => [new QuoteItemRequestInput($lineOfA, 1.0, null), new QuoteItemRequestInput($lineOfA, null, '1')],
            CounterOfferRefusedException::NOTHING_ASKED.' (no request)' => [],
            CounterOfferRefusedException::NOTHING_ASKED.' (nothing on the line)' => [new QuoteItemRequestInput($lineOfA, null, null)],
            CounterOfferRefusedException::INVALID_QUANTITY => [new QuoteItemRequestInput($lineOfA, 0.0, null)],
            CounterOfferRefusedException::INVALID_PRICE => [new QuoteItemRequestInput($lineOfA, null, '-5')],
        ] as $name => $requests) {
            try {
                $this->quotes->counterOffer($quote, $scenario['buyer'], 2, $requests);
                self::fail('The counter-proposal was not refused: '.$name);
            } catch (CounterOfferRefusedException $refused) {
                self::assertSame(explode(' (', $name)[0], $refused->reason(), $name);
            }
        }

        self::assertSame(QuoteState::Quoted->value, $this->reread($quote)->getState(), 'nothing was answered');
        self::assertSame(0, QuoteItemRequestQuery::create()->filterByQuoteItemId($lineOfA)->count());
        self::assertSame([], $this->eventsOf(QuoteEvents::COUNTERED));
    }

    public function testACounterProposalAsksForAPriceAboveZero(): void
    {
        $scenario = $this->sentScenario();
        $quote = $scenario['quote'];
        $line = $this->lineIdOf($quote, $scenario['a']->getRef());

        foreach (['0', '0.000000', '0.0'] as $price) {
            try {
                $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [new QuoteItemRequestInput($line, null, $price)]);
                self::fail('A price of '.$price.' was asked for.');
            } catch (CounterOfferRefusedException $refused) {
                self::assertSame(CounterOfferRefusedException::INVALID_PRICE, $refused->reason(), $price);
            }
        }

        self::assertSame(QuoteState::Quoted->value, $this->reread($quote)->getState());
        self::assertSame(0, QuoteItemRequestQuery::create()->filterByQuoteItemId($line)->count());

        $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [new QuoteItemRequestInput($line, null, '0.01')]);

        self::assertSame(QuoteState::Countered->value, $this->reread($quote)->getState());
    }

    public function testALineThatAsksForWhatTheOfferSaysIsNotDiscussed(): void
    {
        $scenario = $this->sentScenario();
        $quote = $scenario['quote'];
        $lineOfA = $this->lineIdOf($quote, $scenario['a']->getRef());
        $lineOfB = $this->lineIdOf($quote, $scenario['b']->getRef());

        // The offer holds 12 of A at 90 and 4 of B at 45.
        $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [
            new QuoteItemRequestInput($lineOfA, 12.0, '90'),
            new QuoteItemRequestInput($lineOfB, 4.0, '40'),
        ]);

        self::assertSame(0, QuoteItemRequestQuery::create()->filterByQuoteItemId($lineOfA)->count(), 'the line that repeats the offer is not a request');
        self::assertSame(1, QuoteItemRequestQuery::create()->filterByQuoteItemId($lineOfB)->count());
        self::assertSame(QuoteState::Countered->value, $this->reread($quote)->getState());
    }

    public function testACounterProposalThatDiscussesNoLineIsRefused(): void
    {
        $scenario = $this->sentScenario();
        $quote = $scenario['quote'];
        $lineOfA = $this->lineIdOf($quote, $scenario['a']->getRef());
        $lineOfB = $this->lineIdOf($quote, $scenario['b']->getRef());

        foreach ([
            'the quantity and the price of the offer' => [new QuoteItemRequestInput($lineOfA, 12.0, '90.000000'), new QuoteItemRequestInput($lineOfB, 4.0, '45')],
            'the quantity of the offer' => [new QuoteItemRequestInput($lineOfA, 12.0, null)],
            'the price of the offer' => [new QuoteItemRequestInput($lineOfA, null, '90')],
        ] as $name => $requests) {
            try {
                $this->quotes->counterOffer($quote, $scenario['buyer'], 2, $requests);
                self::fail('A counter-proposal that asks for '.$name.' was taken.');
            } catch (CounterOfferRefusedException $refused) {
                self::assertSame(CounterOfferRefusedException::NOTHING_ASKED, $refused->reason(), $name);
            }
        }

        self::assertSame(QuoteState::Quoted->value, $this->reread($quote)->getState(), 'nothing was answered');
        self::assertSame(0, QuoteItemRequestQuery::create()->filterByQuoteItemId([$lineOfA, $lineOfB])->count());
        self::assertSame([], $this->eventsOf(QuoteEvents::COUNTERED));
    }

    public function testABuyerOfASuspendedCompanyCounterProposesNothingButMayStillTurnTheQuoteDown(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $scenario = $this->sentScenario($buyer);
        $quote = $scenario['quote'];
        $line = $this->lineIdOf($quote, $scenario['a']->getRef());
        $this->facade->suspend($company);

        try {
            $this->quotes->counterOffer($quote, $buyer, 2, [new QuoteItemRequestInput($line, 1.0, null)]);
            self::fail('A buyer of a suspended company counter-proposed.');
        } catch (QuoteActionRefusedException $refused) {
            self::assertSame(QuoteActionRefusedException::COMPANY_SUSPENDED, $refused->reason());
        }

        self::assertSame(QuoteState::Quoted->value, $this->reread($quote)->getState());
        self::assertSame(0, QuoteItemRequestQuery::create()->filterByQuoteItemId($line)->count());
        self::assertSame([], $this->eventsOf(QuoteEvents::COUNTERED));

        $this->quotes->reject($quote, $buyer, 2);

        self::assertSame(QuoteState::Rejected->value, $this->reread($quote)->getState());
    }

    public function testOnlyTheRevisionTheQuoteStandsAtCanBeAnswered(): void
    {
        $scenario = $this->sentScenario();
        $quote = $scenario['quote'];

        foreach ([1, 3] as $number) {
            try {
                $this->quotes->reject($quote, $scenario['buyer'], $number);
                self::fail('Revision '.$number.' was answered');
            } catch (QuoteActionRefusedException $refused) {
                self::assertSame(QuoteActionRefusedException::REVISION_STALE, $refused->reason());
            }

            try {
                $this->quotes->counterOffer($quote, $scenario['buyer'], $number, [new QuoteItemRequestInput($this->lineIdOf($quote, $scenario['a']->getRef()), 1.0, null)]);
                self::fail('Revision '.$number.' was countered');
            } catch (QuoteActionRefusedException $refused) {
                self::assertSame(QuoteActionRefusedException::REVISION_STALE, $refused->reason());
            }
        }

        self::assertSame(QuoteState::Quoted->value, $this->reread($quote)->getState());
    }

    public function testAnAnswerIsOnlyAskedWhereTheQuoteWaitsForIt(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];

        // Requested: the shop did not answer yet, so there is nothing to answer.
        try {
            $this->quotes->reject($quote, $scenario['buyer'], 1);
            self::fail('A request was turned down.');
        } catch (QuoteTransitionRefusedException $refused) {
            self::assertSame(QuoteState::Requested, $refused->from());
            self::assertSame(QuoteState::Rejected, $refused->to());
        }

        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($quote, null);
        $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [new QuoteItemRequestInput($this->lineIdOf($quote, $scenario['a']->getRef()), 5.0, null)]);

        // Countered: a second counter-proposal waits for the next revision.
        try {
            $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [new QuoteItemRequestInput($this->lineIdOf($quote, $scenario['a']->getRef()), 6.0, null)]);
            self::fail('A quote was countered twice.');
        } catch (QuoteTransitionRefusedException $refused) {
            self::assertSame(QuoteState::Countered, $refused->from());
            self::assertSame(QuoteState::Countered, $refused->to());
        }
    }

    public function testTheBuyerTurnsDownAQuoteThatWasSentOrCountered(): void
    {
        foreach ([false, true] as $countered) {
            $scenario = $this->sentScenario();
            $quote = $scenario['quote'];

            if ($countered) {
                $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [new QuoteItemRequestInput($this->lineIdOf($quote, $scenario['a']->getRef()), 5.0, null)]);
            }

            $rejected = $this->quotes->reject($quote, $scenario['buyer'], 2);

            self::assertSame(QuoteState::Rejected->value, $rejected->getState());
            self::assertSame(QuoteState::Rejected->value, $this->reread($quote)->getState());
        }

        $heard = $this->eventsOf(QuoteEvents::REJECTED);
        self::assertCount(2, $heard);
        self::assertInstanceOf(QuoteRejectedEvent::class, $heard[0]);
        self::assertSame(2, $heard[0]->getRevisionNumber());

        // And nothing leaves a quote that was turned down.
        try {
            $this->quotes->extend($quote, new \DateTimeImmutable('2027-01-31'));
            self::fail('A rejected quote was extended.');
        } catch (QuoteTransitionRefusedException $refused) {
            self::assertSame(QuoteState::Rejected, $refused->from());
        }
    }

    public function testAnotherCustomerCannotAnswerAndDoesNotLearnTheQuoteExists(): void
    {
        $alice = $this->sentScenario();
        $bob = $this->customer();
        $bobScenario = $this->sentScenario($bob);

        foreach ([[$alice['quote'], $bob, $alice], [$bobScenario['quote'], $alice['buyer'], $bobScenario]] as [$quote, $intruder, $owner]) {
            $line = $this->lineIdOf($quote, $owner['a']->getRef());

            foreach ([
                fn () => $this->quotes->counterOffer($quote, $intruder, 2, [new QuoteItemRequestInput($line, 1.0, null)]),
                fn () => $this->quotes->reject($quote, $intruder, 2),
            ] as $answer) {
                try {
                    $answer();
                    self::fail('A customer answered the quote of another.');
                } catch (UnknownQuoteException $refused) {
                    self::assertSame('quote_not_found', $refused->reason());
                }
            }

            self::assertSame(QuoteState::Quoted->value, $this->reread($quote)->getState());
        }

        self::assertSame([], $this->eventsOf(QuoteEvents::REJECTED));
        self::assertSame([], $this->eventsOf(QuoteEvents::COUNTERED));
    }

    public function testACompanyAnswersTheQuotesOfItsBuyersAndNoOtherCompanyDoes(): void
    {
        $first = $this->company('First');
        $second = $this->company('Second');
        $requester = $this->buyerOf($first);
        $colleague = $this->buyerOf($first, 'company_admin');
        $viewer = $this->buyerOf($first, 'approver');
        $strangerAdmin = $this->buyerOf($second, 'company_admin');
        $scenario = $this->sentScenario($requester);
        $quote = $scenario['quote'];
        self::assertSame($first->getId(), $this->reread($quote)->getCompanyId());
        $line = $this->lineIdOf($quote, $scenario['a']->getRef());

        // Another company: not even a quote.
        foreach ([
            fn () => $this->quotes->counterOffer($quote, $strangerAdmin, 2, [new QuoteItemRequestInput($line, 1.0, null)]),
            fn () => $this->quotes->reject($quote, $strangerAdmin, 2),
        ] as $answer) {
            try {
                $answer();
                self::fail('A buyer of another company answered.');
            } catch (UnknownQuoteException) {
                self::addToAssertionCount(1);
            }
        }

        // The approver of the company reads the quote and holds no right to answer it.
        foreach ([
            fn () => $this->quotes->counterOffer($quote, $viewer, 2, [new QuoteItemRequestInput($line, 1.0, null)]),
            fn () => $this->quotes->reject($quote, $viewer, 2),
        ] as $answer) {
            try {
                $answer();
                self::fail('A colleague without the right answered.');
            } catch (QuoteActionRefusedException $refused) {
                self::assertSame(QuoteActionRefusedException::NOT_PERMITTED, $refused->reason());
            }
        }

        self::assertSame(QuoteState::Quoted->value, $this->reread($quote)->getState());

        // A colleague who may ask for quotes, and read those of the company, answers.
        $this->quotes->counterOffer($quote, $colleague, 2, [new QuoteItemRequestInput($line, 1.0, null)]);

        self::assertSame(QuoteState::Countered->value, $this->reread($quote)->getState());
    }

    public function testABuyerWhoMayNotAskForQuotesAnswersNoQuoteEvenOfTheirOwn(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $scenario = $this->sentScenario($buyer);
        // The role of the buyer is changed by the company after the request.
        $this->facade->changeRole($buyer, 'approver');

        try {
            $this->quotes->reject($scenario['quote'], $buyer, 2);
            self::fail('A buyer without the right answered.');
        } catch (QuoteActionRefusedException $refused) {
            self::assertSame(QuoteActionRefusedException::NOT_PERMITTED, $refused->reason());
        }
    }

    public function testAnAccountWithNoCompanyAlwaysAnswersItsOwnQuotes(): void
    {
        $scenario = $this->sentScenario();

        $this->quotes->reject($scenario['quote'], $scenario['buyer'], 2);

        self::assertSame(QuoteState::Rejected->value, $this->reread($scenario['quote'])->getState());
    }
}
