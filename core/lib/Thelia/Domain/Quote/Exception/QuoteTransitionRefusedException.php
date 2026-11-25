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

namespace Thelia\Domain\Quote\Exception;

use Thelia\Domain\Quote\Enum\QuoteState;

/**
 * The quote is not in a state that leads where the operation wants it to go.
 */
final class QuoteTransitionRefusedException extends QuoteException
{
    public const string REASON_TRANSITION = 'transition_refused';

    public const string REASON_DRAFT = 'draft_refused';

    public const string REASON_DELETION_SENT = 'deletion_refused_sent';

    public const string REASON_DELETION_ORDERED = 'deletion_refused_ordered';

    private function __construct(
        private readonly string $reason,
        string $message,
        private readonly QuoteState $from,
        private readonly ?QuoteState $to,
    ) {
        parent::__construct($message);
    }

    public static function notAllowed(QuoteState $from, QuoteState $to): self
    {
        return new self(self::REASON_TRANSITION, 'The quote is not in a state that allows this.', $from, $to);
    }

    public static function cannotBeDrafted(QuoteState $state): self
    {
        return new self(self::REASON_DRAFT, 'A revision of this quote cannot be written in its current state.', $state, null);
    }

    /**
     * The shop sent a revision of the quote: it is the record of what was offered.
     */
    public static function cannotBeDeletedOnceSent(QuoteState $state): self
    {
        return new self(self::REASON_DELETION_SENT, 'A quote the shop sent cannot be deleted.', $state, null);
    }

    /**
     * An order came from the quote, which that order still names.
     */
    public static function cannotBeDeletedOnceOrdered(QuoteState $state): self
    {
        return new self(self::REASON_DELETION_ORDERED, 'A quote an order came from cannot be deleted.', $state, null);
    }

    public function from(): QuoteState
    {
        return $this->from;
    }

    public function to(): ?QuoteState
    {
        return $this->to;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
