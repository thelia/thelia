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
use Thelia\Domain\Quote\Enum\QuoteRevisionAuthor;
use Thelia\Domain\Quote\Enum\QuoteRevisionState;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Event\QuoteCounteredEvent;
use Thelia\Domain\Quote\Event\QuoteEvents;
use Thelia\Domain\Quote\Event\QuoteExtendedEvent;
use Thelia\Domain\Quote\Event\QuoteSentEvent;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\QuoteItemRequestQuery;
use Thelia\Model\QuoteRevision;
use Thelia\Model\QuoteRevisionQuery;

/**
 * A negotiation, from the request of the buyer to the last sending: three revisions,
 * a counter-proposal that adds nothing and removes nothing, and a validity that ends and is
 * pushed back.
 */
final class QuoteCycleTest extends QuoteDomainTestCase
{
    public function testTheShopQuotesAndTheFirstRevisionStaysWhatTheBuyerAsked(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $before = $this->snapshotOf($this->revisionOf($quote, 1));

        $draft = $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));

        self::assertSame(2, $draft->getNumber());
        self::assertSame(QuoteRevisionState::Draft->value, $draft->getState());
        self::assertSame(QuoteRevisionAuthor::Admin->value, $draft->getAuthor());
        self::assertSame(QuoteState::Requested->value, $this->reread($quote)->getState(), 'the buyer sees nothing yet');
        self::assertSame(1, $this->reread($quote)->getCurrentRevision());
        self::assertSame($before, $this->snapshotOf($this->revisionOf($quote, 1)), 'the request is never written again');

        $lines = $this->linesOf($draft);
        self::assertCount(3, $lines);
        self::assertSame(['100.000000', '50.000000', '20.000000'], array_map(static fn ($line): ?string => $line->getCatalogPrice(), $lines), 'the catalog prices of A and B are those of the request, the one of C is read now');
        self::assertSame(['90.000000', '45.000000', '20.000000'], array_map(static fn ($line): ?string => $line->getPrice(), $lines));
        self::assertSame([12.0, 4.0, 5.0], array_map(static fn ($line): ?float => $line->getQuantity(), $lines));

        $sent = $this->quotes->send($quote, null);

        self::assertSame(QuoteState::Quoted->value, $sent->getState());
        self::assertSame(2, $sent->getCurrentRevision());
        self::assertSame(QuoteRevisionState::Sent->value, $this->revisionOf($quote, 2)->getState());
        self::assertNotNull($this->revisionOf($quote, 2)->getSentAt());
        self::assertSame('2026-12-31', $this->reread($quote)->getValidUntil('Y-m-d'), 'the day of the sending plus thirty days');
        self::assertSame('2026-12-01', $this->reread($quote)->getSentAt('Y-m-d'));

        $heard = $this->eventsOf(QuoteEvents::SENT);
        self::assertCount(1, $heard);
        self::assertInstanceOf(QuoteSentEvent::class, $heard[0]);
        self::assertSame(2, $heard[0]->getRevisionNumber());
        self::assertSame(self::TEST_TRANSACTION_LEVELS, $this->quoteEvents[QuoteEvents::SENT][0]['transactionLevel']);
    }

    public function testACounterProposalAddsNoRevisionAndNoLineAndRemovesNone(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($quote, null);
        $secondRevision = $this->revisionOf($quote, 2);
        $lineOfA = $this->lineOf($secondRevision, $scenario['a']->getRef());
        $linesBefore = $this->snapshotOf($secondRevision);

        $countered = $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [new QuoteItemRequestInput((int) $lineOfA->getId(), null, '85')]);

        self::assertSame(QuoteState::Countered->value, $countered->getState());
        self::assertSame(2, QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->count(), 'no revision is added');
        self::assertSame($linesBefore, $this->snapshotOf($secondRevision), 'the lines of the revision are as they were sent');

        $requests = [...QuoteItemRequestQuery::create()->filterByQuoteItemId($lineOfA->getId())->find()];
        self::assertCount(1, $requests);
        self::assertNull($requests[0]->getQuantity(), 'the quantity is left as it is');
        self::assertSame('85.000000', $requests[0]->getPrice());

        $heard = $this->eventsOf(QuoteEvents::COUNTERED);
        self::assertCount(1, $heard);
        self::assertInstanceOf(QuoteCounteredEvent::class, $heard[0]);
        self::assertSame(2, $heard[0]->getRevisionNumber());
    }

    public function testTheShopSendsAgainAndTheHistoryHoldsThreeRevisions(): void
    {
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($quote, null);
        $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [new QuoteItemRequestInput((int) $this->lineOf($this->revisionOf($quote, 2), $scenario['a']->getRef())->getId(), null, '85')]);

        $third = $this->quotes->saveDraft($quote, null, $this->draftFrom($this->revisionOf($quote, 2), [$scenario['a']->getRef() => ['price' => '87']], discount: '30', postage: '15'));
        $this->quotes->send($quote, null);

        self::assertSame(3, $third->getNumber());
        self::assertSame(3, QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->count());
        self::assertSame(QuoteState::Quoted->value, $this->reread($quote)->getState());
        self::assertSame(3, $this->reread($quote)->getCurrentRevision());
        self::assertSame('1576.800000', $this->revisionOf($quote, 3)->getTotalTaxed());
        self::assertSame('1620.000000', $this->revisionOf($quote, 2)->getTotalTaxed(), 'the revision the shop sent first still says what it said');
        self::assertSame(['87.000000', '45.000000', '20.000000'], array_map(static fn ($line): ?string => $line->getPrice(), $this->linesOf($this->revisionOf($quote, 3))));
        self::assertSame(['100.000000', '50.000000', '20.000000'], array_map(static fn ($line): ?string => $line->getCatalogPrice(), $this->linesOf($this->revisionOf($quote, 3))), 'the catalog prices are carried from revision to revision');
    }

    public function testAQuoteIsAcceptableUntilItsLastDayThenExpiredAndTheShopPushesItBack(): void
    {
        ConfigQuery::write('quote_validity_days', '15');
        $scenario = $this->requestedScenario();
        $quote = $scenario['quote'];
        $this->quotes->saveDraft($quote, null, $this->draftOfTheShop($scenario));
        $this->quotes->send($quote, null);
        self::assertSame('2026-12-16', $this->reread($quote)->getValidUntil('Y-m-d'), 'sent on the 1st, valid for fifteen days');

        self::assertSame(QuoteState::Quoted, $this->quotes->effectiveState($this->reread($quote), new \DateTimeImmutable('2026-12-16')));
        self::assertSame(QuoteState::Expired, $this->quotes->effectiveState($this->reread($quote), new \DateTimeImmutable('2026-12-17')));

        // Past its last day the buyer can no longer answer, although nothing was written yet.
        $this->clock->modify('2026-12-17 08:00:00');
        $this->assertTransitionRefused(fn () => $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [new QuoteItemRequestInput((int) $this->lineOf($this->revisionOf($quote, 2), $scenario['a']->getRef())->getId(), null, '85')]));
        $this->assertTransitionRefused(fn () => $this->quotes->reject($quote, $scenario['buyer'], 2));

        $extended = $this->quotes->extend($quote, new \DateTimeImmutable('2026-12-23'));

        self::assertSame(QuoteState::Quoted->value, $extended->getState());
        self::assertSame('2026-12-23', $this->reread($quote)->getValidUntil('Y-m-d'));
        self::assertSame(QuoteState::Quoted, $this->quotes->effectiveState($this->reread($quote), new \DateTimeImmutable('2026-12-23')));
        self::assertSame(2, $this->reread($quote)->getCurrentRevision(), 'the revision is the same one');

        $heard = $this->eventsOf(QuoteEvents::EXTENDED);
        self::assertCount(1, $heard);
        self::assertInstanceOf(QuoteExtendedEvent::class, $heard[0]);
        self::assertSame(2, $heard[0]->getRevisionNumber());

        // And the buyer answers again.
        $this->quotes->reject($quote, $scenario['buyer'], 2);
        self::assertSame(QuoteState::Rejected->value, $this->reread($quote)->getState());
    }

    /**
     * @param callable(): mixed $action
     */
    private function assertTransitionRefused(callable $action): void
    {
        try {
            $action();
            self::fail('The action was not refused.');
        } catch (QuoteTransitionRefusedException $refused) {
            self::assertSame('transition_refused', $refused->reason());
            self::assertSame(QuoteState::Expired, $refused->from());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotOf(QuoteRevision $revision): array
    {
        return [
            'revision' => QuoteRevisionQuery::create()->findPk($revision->getId())?->toArray(),
            'lines' => array_map(static fn ($line): array => $line->toArray(), $this->linesOf($revision)),
        ];
    }
}
