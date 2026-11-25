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

use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Event\QuoteCreatedEvent;
use Thelia\Domain\Quote\Event\QuoteDeletedEvent;
use Thelia\Domain\Quote\Event\QuoteDraftSavedEvent;
use Thelia\Domain\Quote\Event\QuoteDuplicatedEvent;
use Thelia\Domain\Quote\Event\QuoteEvents;
use Thelia\Domain\Quote\Exception\QuoteDraftRefusedException;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;
use Thelia\Model\QuoteQuery;

/**
 * The shop is told of what it does to a quote as it is told of what the buyer does: once the
 * transaction is committed, and never of a gesture that was refused.
 */
final class QuoteShopEventsTest extends QuoteDomainTestCase
{
    public function testStartingAQuoteForAnAccountTellsTheShopOnceItIsCommitted(): void
    {
        $customer = $this->customer();

        $quote = $this->quotes->createForCustomer($customer, null);

        $heard = $this->eventsOf(QuoteEvents::CREATED);
        self::assertCount(1, $heard);
        self::assertInstanceOf(QuoteCreatedEvent::class, $heard[0]);
        self::assertSame($quote->getId(), $heard[0]->getQuote()->getId());
        self::assertSame(1, $heard[0]->getRevisionNumber());
        self::assertSame(self::TEST_TRANSACTION_LEVELS, $this->quoteEvents[QuoteEvents::CREATED][0]['transactionLevel'], 'told once the transaction is over');
        self::assertMatchesRegularExpression('/^QUO\d{8}$/', $heard[0]->getQuote()->getRef(), 'with the number the quote ends up with');
    }

    public function testAQuoteRefusedToAGuestTellsNothing(): void
    {
        try {
            $this->quotes->createForCustomer($this->guest(), null);
            self::fail('The quote was not refused.');
        } catch (QuoteDraftRefusedException) {
        }

        self::assertSame([], $this->eventsOf(QuoteEvents::CREATED));
    }

    public function testWritingADraftTellsTheShopWhichRevisionItWrote(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];

        $draft = $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));

        $heard = $this->eventsOf(QuoteEvents::DRAFT_SAVED);
        self::assertCount(2, $heard, 'one event per writing');
        self::assertInstanceOf(QuoteDraftSavedEvent::class, $heard[0]);
        self::assertSame($quote->getId(), $heard[0]->getQuote()->getId());
        self::assertSame((int) $draft->getNumber(), $heard[0]->getRevisionNumber());
        self::assertSame(2, $heard[1]->getRevisionNumber(), 'the draft that is rewritten is the same revision');
        self::assertSame(self::TEST_TRANSACTION_LEVELS, $this->quoteEvents[QuoteEvents::DRAFT_SAVED][0]['transactionLevel'], 'told once the transaction is over');
        self::assertSame([], $this->eventsOf(QuoteEvents::SENT), 'a draft is not a sending');
    }

    public function testADraftThatIsRefusedTellsNothing(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];

        try {
            $this->quotes->saveDraft($quote, null, new QuoteDraftInput([new QuoteDraftLine(null, $this->saleElementId($scenario['a']), 0.0, '1')]));
            self::fail('The draft was not refused.');
        } catch (QuoteDraftRefusedException) {
        }

        $this->quotes->saveAndSend($quote, null, $this->draftOfTheShop($scenario));

        try {
            $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
            self::fail('A quote the buyer is looking at takes no draft.');
        } catch (QuoteTransitionRefusedException) {
        }

        self::assertSame([], $this->eventsOf(QuoteEvents::DRAFT_SAVED));
    }

    public function testSavingAndSendingInOneGestureTellsTheSendingAndNothingMore(): void
    {
        $scenario = $this->requestedScenario();

        $this->quotes->saveAndSend($scenario['quote'], null, $this->draftOfTheShop($scenario));

        self::assertCount(1, $this->eventsOf(QuoteEvents::SENT));
        self::assertSame([], $this->eventsOf(QuoteEvents::DRAFT_SAVED), 'the draft never stood on its own');
    }

    public function testACopyTellsTheShopWhichQuoteWasCopiedAndWhichOneItIs(): void
    {
        $scenario = $this->requestedScenario();
        $source = $scenario['quote'];
        $this->quotes->saveAndSend($source, null, $this->draftOfTheShop($scenario));

        $copy = $this->quotes->duplicate($source, null)->quote;

        $heard = $this->eventsOf(QuoteEvents::DUPLICATED);
        self::assertCount(1, $heard);
        self::assertInstanceOf(QuoteDuplicatedEvent::class, $heard[0]);
        self::assertSame($source->getId(), $heard[0]->getOriginal()->getId());
        self::assertSame($copy->getId(), $heard[0]->getQuote()->getId());
        self::assertNotSame($heard[0]->getOriginal()->getId(), $heard[0]->getQuote()->getId());
        self::assertSame(QuoteState::Draft->value, $heard[0]->getQuote()->getState());
        self::assertSame(1, $heard[0]->getRevisionNumber(), 'the draft of the copy');
        self::assertSame(self::TEST_TRANSACTION_LEVELS, $this->quoteEvents[QuoteEvents::DUPLICATED][0]['transactionLevel'], 'told once the transaction is over');
        self::assertSame([], $this->eventsOf(QuoteEvents::CREATED), 'a copy is one event, not the three of its parts');
        self::assertSame([], $this->eventsOf(QuoteEvents::DRAFT_SAVED));
    }

    public function testACopyThatIsRefusedTellsNothing(): void
    {
        $scenario = $this->requestedScenario();
        $scenario['buyer']->setIsGuest(1)->save($this->getPropelConnection());

        try {
            $this->quotes->duplicate($scenario['quote'], null);
            self::fail('The copy was not refused.');
        } catch (QuoteDraftRefusedException) {
        }

        self::assertSame([], $this->eventsOf(QuoteEvents::DUPLICATED));
    }

    public function testDeletingAQuoteTheShopNeverSentTellsTheShopOnceItIsCommitted(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $id = (int) $quote->getId();
        $ref = $quote->getRef();

        $this->quotes->delete($quote);

        $heard = $this->eventsOf(QuoteEvents::DELETED);
        self::assertCount(1, $heard);
        self::assertInstanceOf(QuoteDeletedEvent::class, $heard[0]);
        self::assertSame($id, $heard[0]->getQuote()->getId());
        self::assertSame($ref, $heard[0]->getQuote()->getRef(), 'the quote still says what it was');
        self::assertSame(1, $heard[0]->getRevisionNumber());
        self::assertSame(self::TEST_TRANSACTION_LEVELS, $this->quoteEvents[QuoteEvents::DELETED][0]['transactionLevel'], 'told once the transaction is over');
        self::assertNull(QuoteQuery::create()->findPk($id));
    }

    public function testDeletingAQuoteTheShopSentTellsNothingAndKeepsIt(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $this->quotes->saveAndSend($quote, null, $this->draftOfTheShop($scenario));

        try {
            $this->quotes->delete($quote);
            self::fail('The deletion was not refused.');
        } catch (QuoteTransitionRefusedException $refused) {
            self::assertSame(QuoteTransitionRefusedException::REASON_DELETION_SENT, $refused->reason());
            self::assertSame(QuoteState::Quoted, $refused->from());
        }

        self::assertSame([], $this->eventsOf(QuoteEvents::DELETED));
        self::assertNotNull(QuoteQuery::create()->findPk($quote->getId()));
    }
}
