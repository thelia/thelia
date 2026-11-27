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

namespace Thelia\Domain\Quote;

use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;

/**
 * The map of the states of a quote: where each one can go, and nowhere else.
 *
 * A quote the shop wrote starts as a draft; a quote a buyer asked for starts as requested. Each
 * sending of the shop leads to quoted, from which the buyer accepts, counters or turns it down,
 * and which the validity can end. A counter-proposal waits for a new sending or is given up; an
 * expired quote comes back when the shop sends it again or pushes its validity back.
 *
 * An order that is cancelled gives its quote back to the buyer, who may try the payment again
 * (ordered to accepted), and an order that comes back to life takes it again (accepted to ordered).
 * That is the one way out of ordered, and it belongs to the order: a buyer who answers an ordered
 * quote is refused by the facade. Nothing writes the state of a quote but the facade and the
 * listener of the orders, and both ask this map first.
 *
 * An expiry is read, never waited for: a quote is expired as soon as its last valid day is past,
 * whether the console has written it yet or not, see {@see effectiveState()}.
 */
final readonly class QuoteTransitions
{
    /**
     * @var array<string, list<QuoteState>>
     */
    private const array MAP = [
        'draft' => [QuoteState::Quoted],
        'requested' => [QuoteState::Quoted],
        'quoted' => [QuoteState::Countered, QuoteState::Accepted, QuoteState::Rejected, QuoteState::Expired],
        'countered' => [QuoteState::Quoted, QuoteState::Rejected],
        'expired' => [QuoteState::Quoted],
        'accepted' => [QuoteState::Ordered],
        'rejected' => [],
        'ordered' => [QuoteState::Accepted],
    ];

    /**
     * The states a revision of the shop can be written in.
     *
     * @var list<QuoteState>
     */
    private const array DRAFTABLE = [QuoteState::Draft, QuoteState::Requested, QuoteState::Countered, QuoteState::Expired];

    public function canMove(QuoteState $from, QuoteState $to): bool
    {
        return \in_array($to, self::MAP[$from->value], true);
    }

    /**
     * @throws QuoteTransitionRefusedException
     */
    public function assertCanMove(QuoteState $from, QuoteState $to): void
    {
        if (!$this->canMove($from, $to)) {
            throw QuoteTransitionRefusedException::notAllowed($from, $to);
        }
    }

    /**
     * @return list<QuoteState>
     */
    public function movesFrom(QuoteState $from): array
    {
        return self::MAP[$from->value];
    }

    public function canBeDrafted(QuoteState $state): bool
    {
        return \in_array($state, self::DRAFTABLE, true);
    }

    /**
     * @throws QuoteTransitionRefusedException
     */
    public function assertCanBeDrafted(QuoteState $state): void
    {
        if (!$this->canBeDrafted($state)) {
            throw QuoteTransitionRefusedException::cannotBeDrafted($state);
        }
    }

    /**
     * The state a quote is in on the given day: a quote sent and still waiting is expired once
     * the last day it could be accepted is behind. That day itself still counts.
     */
    public function effectiveState(QuoteState $stored, ?\DateTimeInterface $validUntil, \DateTimeInterface $today): QuoteState
    {
        if (QuoteState::Quoted === $stored
            && null !== $validUntil
            && $validUntil->format('Y-m-d') < $today->format('Y-m-d')) {
            return QuoteState::Expired;
        }

        return $stored;
    }
}
