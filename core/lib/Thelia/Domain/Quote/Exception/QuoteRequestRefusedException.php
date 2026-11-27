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
 * A buyer cannot turn this cart into a request for a quote.
 */
final class QuoteRequestRefusedException extends QuoteException
{
    public const string QUOTE_DISABLED = 'quote_disabled';
    public const string CART_EMPTY = 'cart_empty';
    public const string NOT_PERMITTED = 'not_permitted';
    public const string CART_NOT_OWNED = 'cart_not_owned';
    public const string LINE_INVALID = 'line_invalid';
    public const string ADDRESS_REFUSED = 'address_refused';
    public const string NEEDED_BY_PAST = 'needed_by_past';
    public const string QUOTE_CART = 'quote_cart';
    public const string COMPANY_SUSPENDED = 'company_suspended';

    /**
     * @param list<int> $cartItemIds the lines that cannot be quoted
     */
    private function __construct(private readonly string $reason, string $message, private readonly array $cartItemIds = [])
    {
        parent::__construct($message);
    }

    public static function quotesAreDisabled(): self
    {
        return new self(self::QUOTE_DISABLED, 'This shop does not take requests for quotes.');
    }

    public static function emptyCart(): self
    {
        return new self(self::CART_EMPTY, 'A quote cannot be asked for an empty cart.');
    }

    public static function notPermitted(): self
    {
        return new self(self::NOT_PERMITTED, 'Your role in your company does not allow you to ask for quotes.');
    }

    public static function cartNotOwned(): self
    {
        return new self(self::CART_NOT_OWNED, 'This cart does not belong to you.');
    }

    /**
     * @param list<int> $cartItemIds
     */
    public static function invalidLines(array $cartItemIds): self
    {
        return new self(self::LINE_INVALID, 'Some lines of the cart cannot be quoted: their product is no longer sold or cannot be priced for a quote.', $cartItemIds);
    }

    public static function addressRefused(): self
    {
        return new self(self::ADDRESS_REFUSED, 'You may not use this address on a quote.');
    }

    public static function neededByPast(): self
    {
        return new self(self::NEEDED_BY_PAST, 'The day the goods are needed by cannot be in the past.');
    }

    public static function cartOfAQuote(): self
    {
        return new self(self::QUOTE_CART, 'The cart of a quote cannot be turned into a new request for a quote.');
    }

    public static function companySuspended(): self
    {
        return new self(self::COMPANY_SUSPENDED, 'Your company account is suspended: quotes cannot be asked for or answered until it is reactivated.');
    }

    /**
     * @return list<int>
     */
    public function cartItemIds(): array
    {
        return $this->cartItemIds;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
