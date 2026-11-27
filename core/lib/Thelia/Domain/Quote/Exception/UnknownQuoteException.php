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

/**
 * There is no such quote for the caller: it does not exist, or it belongs to someone the caller
 * may not read, and the two are not told apart.
 */
final class UnknownQuoteException extends QuoteException
{
    public const string REASON = 'quote_not_found';

    private function __construct(private readonly int $quoteId)
    {
        parent::__construct('The quote was not found.');
    }

    public static function withId(int $quoteId): self
    {
        return new self($quoteId);
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function reason(): string
    {
        return self::REASON;
    }
}
