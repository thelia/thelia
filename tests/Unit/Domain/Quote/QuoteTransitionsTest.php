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

namespace Thelia\Tests\Unit\Domain\Quote;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;
use Thelia\Domain\Quote\QuoteTransitions;

/**
 * Where a quote can go, and nowhere else.
 */
final class QuoteTransitionsTest extends TestCase
{
    /**
     * @return iterable<string, array{QuoteState, QuoteState}>
     */
    public static function allowedMoves(): iterable
    {
        foreach ([
            'draft' => ['quoted'],
            'requested' => ['quoted'],
            'quoted' => ['countered', 'accepted', 'rejected', 'expired'],
            'countered' => ['quoted', 'rejected'],
            'expired' => ['quoted'],
            'accepted' => ['ordered'],
            'ordered' => ['accepted'],
        ] as $from => $targets) {
            foreach ($targets as $to) {
                yield $from.' -> '.$to => [QuoteState::from($from), QuoteState::from($to)];
            }
        }
    }

    /**
     * Every pair of states the map does not lead to, a state to itself included.
     *
     * @return iterable<string, array{QuoteState, QuoteState}>
     */
    public static function forbiddenMoves(): iterable
    {
        $allowed = [];

        foreach (self::allowedMoves() as [$from, $to]) {
            $allowed[$from->value.'>'.$to->value] = true;
        }

        foreach (QuoteState::cases() as $from) {
            foreach (QuoteState::cases() as $to) {
                if (!isset($allowed[$from->value.'>'.$to->value])) {
                    yield $from->value.' -> '.$to->value => [$from, $to];
                }
            }
        }
    }

    #[DataProvider('allowedMoves')]
    public function testAnAllowedMoveIsAccepted(QuoteState $from, QuoteState $to): void
    {
        $transitions = new QuoteTransitions();

        self::assertTrue($transitions->canMove($from, $to));
        $transitions->assertCanMove($from, $to);
        self::assertContains($to, $transitions->movesFrom($from));
    }

    #[DataProvider('forbiddenMoves')]
    public function testAnyOtherMoveIsRefusedWithAStableReason(QuoteState $from, QuoteState $to): void
    {
        $transitions = new QuoteTransitions();

        self::assertFalse($transitions->canMove($from, $to));

        try {
            $transitions->assertCanMove($from, $to);
            self::fail('The move was not refused.');
        } catch (QuoteTransitionRefusedException $refused) {
            self::assertSame('transition_refused', $refused->reason());
            self::assertSame($from, $refused->from());
            self::assertSame($to, $refused->to());
        }
    }

    public function testTheMapHasNoDeadEndButTheRejectedState(): void
    {
        $transitions = new QuoteTransitions();

        self::assertSame([], $transitions->movesFrom(QuoteState::Rejected), 'rejected is final');

        foreach (QuoteState::cases() as $state) {
            if (QuoteState::Rejected !== $state) {
                self::assertNotSame([], $transitions->movesFrom($state), $state->value.' leads somewhere');
            }
        }
    }

    public function testAnOrderThatIsCancelledGivesTheQuoteBackAndAnOrderThatComesBackTakesItAgain(): void
    {
        $transitions = new QuoteTransitions();

        self::assertTrue($transitions->canMove(QuoteState::Ordered, QuoteState::Accepted));
        self::assertTrue($transitions->canMove(QuoteState::Accepted, QuoteState::Ordered));
        self::assertSame([QuoteState::Accepted], $transitions->movesFrom(QuoteState::Ordered), 'an ordered quote goes nowhere else');
        self::assertSame([QuoteState::Ordered], $transitions->movesFrom(QuoteState::Accepted), 'an accepted quote goes nowhere else');
    }

    public function testAQuoteThatWasCounteredCanBeTurnedDown(): void
    {
        $transitions = new QuoteTransitions();

        self::assertTrue($transitions->canMove(QuoteState::Countered, QuoteState::Rejected));
        self::assertSame([QuoteState::Quoted, QuoteState::Rejected], $transitions->movesFrom(QuoteState::Countered));
    }

    public function testOnlyTheStatesWhereTheShopCanAnswerCanBeDrafted(): void
    {
        $transitions = new QuoteTransitions();

        foreach ([QuoteState::Draft, QuoteState::Requested, QuoteState::Countered, QuoteState::Expired] as $state) {
            self::assertTrue($transitions->canBeDrafted($state), $state->value);
            $transitions->assertCanBeDrafted($state);
        }

        foreach ([QuoteState::Quoted, QuoteState::Accepted, QuoteState::Rejected, QuoteState::Ordered] as $state) {
            self::assertFalse($transitions->canBeDrafted($state), $state->value);

            try {
                $transitions->assertCanBeDrafted($state);
                self::fail('A draft was accepted for '.$state->value);
            } catch (QuoteTransitionRefusedException $refused) {
                self::assertSame('draft_refused', $refused->reason());
            }
        }
    }

    public function testAQuoteIsExpiredTheDayAfterItsLastValidDay(): void
    {
        $transitions = new QuoteTransitions();
        $validUntil = new \DateTimeImmutable('2026-12-16');

        self::assertSame(QuoteState::Quoted, $transitions->effectiveState(QuoteState::Quoted, $validUntil, new \DateTimeImmutable('2026-12-15 23:59:59')));
        self::assertSame(QuoteState::Quoted, $transitions->effectiveState(QuoteState::Quoted, $validUntil, new \DateTimeImmutable('2026-12-16 23:59:59')), 'the last day still counts');
        self::assertSame(QuoteState::Expired, $transitions->effectiveState(QuoteState::Quoted, $validUntil, new \DateTimeImmutable('2026-12-17 00:00:00')));
    }

    public function testOnlyAQuoteWaitingForTheBuyerExpiresByTheCalendar(): void
    {
        $transitions = new QuoteTransitions();
        $past = new \DateTimeImmutable('2020-01-01');
        $today = new \DateTimeImmutable('2026-12-17');

        foreach (QuoteState::cases() as $state) {
            if (QuoteState::Quoted === $state) {
                continue;
            }

            self::assertSame($state, $transitions->effectiveState($state, $past, $today), $state->value.' stays what it is');
        }

        self::assertSame(QuoteState::Quoted, $transitions->effectiveState(QuoteState::Quoted, null, $today), 'no last day, no expiry');
    }
}
