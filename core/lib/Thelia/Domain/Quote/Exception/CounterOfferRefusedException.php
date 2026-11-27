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
 * The counter-proposal asks for what a counter-proposal cannot: it adds, removes or leaves
 * unchanged a line, or asks for a quantity or a price that makes no sense.
 */
final class CounterOfferRefusedException extends QuoteException
{
    public const string NOTHING_ASKED = 'counter_nothing_asked';
    public const string UNKNOWN_LINE = 'counter_unknown_line';
    public const string DUPLICATE_LINE = 'counter_duplicate_line';
    public const string INVALID_QUANTITY = 'counter_invalid_quantity';
    public const string INVALID_PRICE = 'counter_invalid_price';

    private function __construct(private readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function nothingAsked(): self
    {
        return new self(self::NOTHING_ASKED, 'A counter-proposal must ask for a quantity or a price on at least one line.');
    }

    public static function unknownLine(): self
    {
        return new self(self::UNKNOWN_LINE, 'A counter-proposal can only discuss the lines of the revision it answers.');
    }

    public static function duplicateLine(): self
    {
        return new self(self::DUPLICATE_LINE, 'A line can be discussed once in a counter-proposal.');
    }

    public static function invalidQuantity(): self
    {
        return new self(self::INVALID_QUANTITY, 'The quantity asked for must be a positive number.');
    }

    public static function invalidPrice(): self
    {
        return new self(self::INVALID_PRICE, 'The price asked for must be a positive amount.');
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
