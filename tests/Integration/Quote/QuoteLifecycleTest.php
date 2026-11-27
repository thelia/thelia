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

use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Command\QuoteExpireCommand;
use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\DTO\QuoteItemRequestInput;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Event\QuoteEvents;
use Thelia\Domain\Quote\Event\QuoteExpiredEvent;
use Thelia\Domain\Quote\Event\QuoteRemindedEvent;
use Thelia\Domain\Quote\Exception\QuoteDraftRefusedException;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteQuery;

/**
 * The sending, the validity and the reminders of the shop: where each of them leads from, what
 * they refuse, and the expiry the console writes.
 */
final class QuoteLifecycleTest extends QuoteDomainTestCase
{
    public function testTheShopSendsFromEveryStateThatWaitsForIt(): void
    {
        // Draft: a quote of the shop.
        $draft = $this->quotes->createForCustomer($this->customer(), null);
        $this->quotes->saveDraft($draft, null, new QuoteDraftInput([new QuoteDraftLine(null, $this->saleElementId($this->product('10', 'Pen')), 1.0, '9')]));
        self::assertSame(QuoteState::Quoted->value, $this->quotes->send($draft, null)->getState());

        // Requested.
        $scenario = $this->requestedScenario();
        $this->quotes->saveDraft($scenario['quote'], null, $this->draftOfTheShop($scenario));
        self::assertSame(QuoteState::Quoted->value, $this->quotes->send($scenario['quote'], null)->getState());

        // Expired, with a new revision: the quote is quoted again at the new revision.
        $this->clock->modify('2027-02-01 09:00:00');
        $this->quotes->expireOverdue($this->clock->now());
        self::assertSame(QuoteState::Expired->value, $this->reread($scenario['quote'])->getState());
        $this->quotes->saveDraft($scenario['quote'], null, $this->draftFrom($this->revisionOf($scenario['quote'], 2), discount: '30', postage: '15'));
        $resent = $this->quotes->send($scenario['quote'], null);
        self::assertSame(QuoteState::Quoted->value, $resent->getState());
        self::assertSame(3, $resent->getCurrentRevision());
        self::assertSame('2027-03-03', $this->reread($scenario['quote'])->getValidUntil('Y-m-d'), 'thirty days from the new sending');
    }

    public function testTheShopCannotSendWhatWaitsForTheBuyerOrIsOver(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($quote, null);

        $this->assertSendRefusedFrom($quote, QuoteState::Quoted);

        $this->quotes->reject($quote, $scenario['buyer'], 2);
        $this->assertSendRefusedFrom($quote, QuoteState::Rejected);
    }

    public function testThereMustBeADraftWithALineAndAValidLastDayToSend(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];

        $this->assertSendRefused($quote, QuoteDraftRefusedException::NOTHING_TO_SEND);

        $draft = $this->quotes->saveDraft($quote, null, new QuoteDraftInput([]));
        $this->assertSendRefused($quote, QuoteDraftRefusedException::NO_LINES);

        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        // The line of C loses its product between the writing and the sending.
        $line = $this->lineOf($draft, $scenario['c']->getRef());
        $line->setProductSaleElementsId(null)->setProductId(null)->save($this->getPropelConnection());
        $this->assertSendRefused($quote, QuoteDraftRefusedException::LINE_UNAVAILABLE);
        $line->setProductSaleElementsId($this->saleElementId($scenario['c']))->setProductId($scenario['c']->getId())->save($this->getPropelConnection());

        // A last day that was valid when it was written, and is past when the quote is sent.
        $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 2), validUntil: new \DateTimeImmutable('2026-12-02')));
        $this->clock->modify('2026-12-03 09:00:00');
        $this->assertSendRefused($quote, QuoteDraftRefusedException::VALIDITY_IN_THE_PAST);

        self::assertSame(QuoteState::Requested->value, $this->reread($quote)->getState(), 'nothing was sent');
        self::assertSame([], $this->eventsOf(QuoteEvents::SENT));
    }

    public function testTheDayChosenWithTheDraftIsTheLastDayOfTheQuote(): void
    {
        $scenario = $this->requestedScenario();
        $this->quotes->saveDraft($scenario['quote'], null, $this->draftFrom($this->revisionOf($scenario['quote'], 1), validUntil: new \DateTimeImmutable('2026-12-24')));

        $this->quotes->send($scenario['quote'], null);

        self::assertSame('2026-12-24', $this->reread($scenario['quote'])->getValidUntil('Y-m-d'));
    }

    public function testTheTaxRuleOfTheDayIsFrozenOnTheLinesWhenTheyAreSent(): void
    {
        $scenario = $this->requestedScenario();
        $draft = $this->quotes->saveDraft($scenario['quote'], null, $this->draftOfTheShop($scenario));
        $line = $this->lineOf($draft, $scenario['a']->getRef());
        $line->setTaxRuleId(null)->setTaxRuleTitle('stale')->save($this->getPropelConnection());

        $this->quotes->send($scenario['quote'], null);

        $stored = QuoteItemQuery::create()->findPk($line->getId());
        self::assertSame($this->vatRule()->getId(), $stored?->getTaxRuleId());
        self::assertNotSame('stale', $stored->getTaxRuleTitle());
    }

    public function testTheShopPushesTheLastDayBackOfAQuoteThatStillWaits(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($quote, null);

        $this->quotes->extend($quote, new \DateTimeImmutable('2027-03-01'));

        self::assertSame('2027-03-01', $this->reread($quote)->getValidUntil('Y-m-d'));
        self::assertSame(QuoteState::Quoted->value, $this->reread($quote)->getState());
    }

    public function testAQuoteThatIsStillValidIsOnlyExtendedToALaterDay(): void
    {
        $quote = $this->quoted('2026-12-31');

        foreach (['2026-12-31', '2026-12-20', '2026-12-02'] as $day) {
            try {
                $this->quotes->extend($quote, new \DateTimeImmutable($day));
                self::fail('A quote still valid was shortened or left as it is by an extension to '.$day);
            } catch (QuoteDraftRefusedException $refused) {
                self::assertSame(QuoteDraftRefusedException::VALIDITY_NOT_EXTENDED, $refused->reason(), $day);
            }
        }

        self::assertSame('2026-12-31', $this->reread($quote)->getValidUntil('Y-m-d'));
        self::assertSame([], $this->eventsOf(QuoteEvents::EXTENDED));

        $this->quotes->extend($quote, new \DateTimeImmutable('2027-01-01'));

        self::assertSame('2027-01-01', $this->reread($quote)->getValidUntil('Y-m-d'));
        self::assertCount(1, $this->eventsOf(QuoteEvents::EXTENDED));
    }

    public function testNothingIsExtendedThatDoesNotWaitForTheBuyerOrIsOver(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];

        try {
            $this->quotes->extend($quote, new \DateTimeImmutable('2027-03-01'));
            self::fail('A requested quote was extended.');
        } catch (QuoteTransitionRefusedException $refused) {
            self::assertSame(QuoteState::Requested, $refused->from());
        }

        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($quote, null);

        try {
            $this->quotes->extend($quote, new \DateTimeImmutable('2026-11-30'));
            self::fail('A quote was extended to a day already past.');
        } catch (QuoteDraftRefusedException $refused) {
            self::assertSame(QuoteDraftRefusedException::VALIDITY_IN_THE_PAST, $refused->reason());
        }

        self::assertSame([], $this->eventsOf(QuoteEvents::EXTENDED));
    }

    public function testTheShopRemindsTheBuyerOfAQuoteThatWaits(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($quote, null);
        $this->clock->modify('2026-12-10 14:30:00');

        $this->quotes->remind($quote);

        self::assertSame('2026-12-10 14:30:00', $this->reread($quote)->getRemindedAt('Y-m-d H:i:s'));
        $heard = $this->eventsOf(QuoteEvents::REMINDED);
        self::assertCount(1, $heard);
        self::assertInstanceOf(QuoteRemindedEvent::class, $heard[0]);
        self::assertSame(2, $heard[0]->getRevisionNumber());
        self::assertSame(self::TEST_TRANSACTION_LEVELS, $this->quoteEvents[QuoteEvents::REMINDED][0]['transactionLevel']);
    }

    public function testANewSendingStartsTheRemindersOver(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($quote, null);
        $this->quotes->remind($quote);
        self::assertNotNull($this->reread($quote)->getRemindedAt());
        $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [new QuoteItemRequestInput((int) $this->lineOf($this->revisionOf($quote, 2), $scenario['a']->getRef())->getId(), null, '85')]);

        $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 2), discount: '30', postage: '15'));
        $this->quotes->send($quote, null);

        self::assertNull($this->reread($quote)->getRemindedAt());
    }

    public function testNoReminderGoesForAQuoteThatDoesNotWaitOrIsExpired(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];

        try {
            $this->quotes->remind($quote);
            self::fail('A requested quote was reminded.');
        } catch (QuoteTransitionRefusedException $refused) {
            self::assertSame(QuoteState::Requested, $refused->from());
        }

        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($quote, null);
        $this->clock->modify('2027-02-01 09:00:00');

        try {
            $this->quotes->remind($quote);
            self::fail('An expired quote was reminded.');
        } catch (QuoteTransitionRefusedException $refused) {
            self::assertSame(QuoteState::Expired, $refused->from());
        }

        self::assertNull($this->reread($quote)->getRemindedAt());
        self::assertSame([], $this->eventsOf(QuoteEvents::REMINDED));
    }

    public function testTheExpiryIsWrittenOnceAndOnlyForTheQuotesThatWaitPastTheirLastDay(): void
    {
        $late = $this->quoted('2026-12-16');
        $onTheLastDay = $this->quoted('2026-12-17');
        $open = $this->quoted('2026-12-31');
        $requested = $this->requestedScenario()['quote'];
        $requested->setValidUntil('2026-01-01')->save($this->getPropelConnection());
        $before = QuoteQuery::create()->count();

        self::assertSame(1, $this->quotes->expireOverdue(new \DateTimeImmutable('2026-12-17')));
        self::assertSame(0, $this->quotes->expireOverdue(new \DateTimeImmutable('2026-12-17')), 'a second run finds nothing');

        self::assertSame(QuoteState::Expired->value, $this->reread($late)->getState());
        self::assertSame(QuoteState::Quoted->value, $this->reread($onTheLastDay)->getState(), 'the last day itself is still valid');
        self::assertSame(QuoteState::Quoted->value, $this->reread($open)->getState());
        self::assertSame(QuoteState::Requested->value, $this->reread($requested)->getState(), 'only a quote that was sent expires');
        self::assertSame($before, QuoteQuery::create()->count());
        self::assertSame(1, $this->quotes->expireOverdue(new \DateTimeImmutable('2027-01-01')) - 1, 'the two others follow with the calendar');
    }

    public function testTheShopIsToldOfEveryQuoteTheExpiryWritesOnceTheyAreCommitted(): void
    {
        $late = $this->quoted('2026-12-16');
        $later = $this->quoted('2026-12-10');
        $onTheLastDay = $this->quoted('2026-12-17');
        $decided = $this->requestedScenario();
        $this->quotes->saveDraft($decided['quote'], null, $this->draftOfTheShop($decided));
        $this->quotes->send($decided['quote'], null);
        $this->quotes->reject($decided['quote'], $decided['buyer'], 2);
        $decided['quote']->setValidUntil('2026-12-01')->save($this->getPropelConnection());

        self::assertSame(2, $this->quotes->expireOverdue(new \DateTimeImmutable('2026-12-17')));

        $heard = $this->quoteEvents[QuoteEvents::EXPIRED];
        self::assertCount(2, $heard, 'one event per quote written, none for the quote that is still valid or was decided');
        $ids = [];

        foreach ($heard as $one) {
            self::assertInstanceOf(QuoteExpiredEvent::class, $one['event']);
            self::assertSame(2, $one['event']->getRevisionNumber());
            self::assertSame(QuoteState::Expired->value, $one['event']->getQuote()->getState(), 'told once the state is written');
            self::assertSame(self::TEST_TRANSACTION_LEVELS, $one['transactionLevel'], 'told once the transaction is committed');
            $ids[] = $one['event']->getQuote()->getId();
        }

        self::assertEqualsCanonicalizing([$late->getId(), $later->getId()], $ids);
        self::assertNotContains($onTheLastDay->getId(), $ids);

        self::assertSame(0, $this->quotes->expireOverdue(new \DateTimeImmutable('2026-12-17')));
        self::assertCount(2, $this->quoteEvents[QuoteEvents::EXPIRED], 'a second run tells nothing');
    }

    public function testTheConsoleWritesTheExpiryAndCanRunAgain(): void
    {
        $quote = $this->quoted('2020-01-01');
        QuoteTableMap::clearInstancePool();
        $tester = new CommandTester($this->getService(QuoteExpireCommand::class));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('1 quote(s) expired.', $tester->getDisplay());
        self::assertSame(QuoteState::Expired->value, $this->reread($quote)->getState());

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('No quote to expire.', $tester->getDisplay());
    }

    private function quoted(string $validUntil): Quote
    {
        $scenario = $this->requestedScenario();
        $this->quotes->saveDraft($scenario['quote'], null, $this->draftOfTheShop($scenario));
        $this->quotes->send($scenario['quote'], null);
        $scenario['quote']->setValidUntil($validUntil)->save($this->getPropelConnection());

        return $scenario['quote'];
    }

    private function assertSendRefusedFrom(Quote $quote, QuoteState $state): void
    {
        try {
            $this->quotes->send($quote, null);
            self::fail('A quote that is '.$state->value.' was sent.');
        } catch (QuoteTransitionRefusedException $refused) {
            self::assertSame($state, $refused->from());
            self::assertSame(QuoteState::Quoted, $refused->to());
        }
    }

    private function assertSendRefused(Quote $quote, string $reason): void
    {
        try {
            $this->quotes->send($quote, null);
            self::fail('The sending was not refused: '.$reason);
        } catch (QuoteDraftRefusedException $refused) {
            self::assertSame($reason, $refused->reason());
        }
    }
}
