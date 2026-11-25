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
 * Some lines of the quote no longer have anything to sell: their sale element or their product is
 * gone, or the product cannot be ordered as a simple line. The quote stays readable; the lines
 * that stand in the way are named so that the buyer and the shop know what to ask for again.
 */
final class QuoteLinesUnavailableException extends QuoteException
{
    public const string REASON = 'quote_lines_unavailable';

    /**
     * @param list<array{reference: string, title: string}> $lines the product reference and the title the line was written with
     */
    public function __construct(private readonly array $lines)
    {
        parent::__construct('Some lines of this quote are no longer sold, so it cannot be accepted.');
    }

    /**
     * @return list<array{reference: string, title: string}>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function reason(): string
    {
        return self::REASON;
    }
}
