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
 * What the shop wrote on a revision cannot be kept or sent.
 */
final class QuoteDraftRefusedException extends QuoteException
{
    public const string REVISION_LOCKED = 'revision_locked';
    public const string CUSTOMER_REFUSED = 'customer_cannot_have_quotes';
    public const string UNKNOWN_LINE = 'draft_unknown_line';
    public const string UNKNOWN_SALE_ELEMENT = 'draft_unknown_sale_element';
    public const string NO_CATALOG_PRICE = 'draft_no_catalog_price';
    public const string INVALID_QUANTITY = 'draft_invalid_quantity';
    public const string INVALID_PRICE = 'draft_invalid_price';
    public const string INVALID_DISCOUNT = 'draft_invalid_discount';
    public const string INVALID_POSTAGE = 'draft_invalid_postage';
    public const string UNKNOWN_DELIVERY_MODULE = 'draft_unknown_delivery_module';
    public const string NOTE_TOO_LONG = 'draft_note_too_long';
    public const string VALIDITY_IN_THE_PAST = 'validity_in_the_past';
    public const string NOTHING_TO_SEND = 'nothing_to_send';
    public const string NO_LINES = 'no_lines';
    public const string DISCOUNT_ABOVE_TOTAL = 'discount_above_total';
    public const string LINE_UNAVAILABLE = 'line_unavailable';
    public const string VALIDITY_NOT_EXTENDED = 'validity_not_extended';

    private function __construct(private readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function revisionLocked(): self
    {
        return new self(self::REVISION_LOCKED, 'A revision that was sent cannot be changed any more.');
    }

    public static function customerCannotHaveQuotes(): self
    {
        return new self(self::CUSTOMER_REFUSED, 'A quote can only be written for an account that can sign in.');
    }

    public static function unknownLine(): self
    {
        return new self(self::UNKNOWN_LINE, 'A line of the quote is not one of this quote.');
    }

    public static function unknownSaleElement(): self
    {
        return new self(self::UNKNOWN_SALE_ELEMENT, 'A line of the quote names a product that does not exist.');
    }

    public static function noCatalogPrice(): self
    {
        return new self(self::NO_CATALOG_PRICE, 'A line of the quote names a product that has no price in the currency of the quote.');
    }

    public static function invalidQuantity(): self
    {
        return new self(self::INVALID_QUANTITY, 'The quantity of a line must be a positive number.');
    }

    public static function invalidPrice(): self
    {
        return new self(self::INVALID_PRICE, 'The price of a line must be a positive amount.');
    }

    public static function invalidDiscount(): self
    {
        return new self(self::INVALID_DISCOUNT, 'The discount must be a positive amount.');
    }

    public static function invalidPostage(): self
    {
        return new self(self::INVALID_POSTAGE, 'The postage must be a positive amount.');
    }

    public static function unknownDeliveryModule(): self
    {
        return new self(self::UNKNOWN_DELIVERY_MODULE, 'The delivery module of the quote is not a delivery module of the shop.');
    }

    public static function noteTooLong(): self
    {
        return new self(self::NOTE_TOO_LONG, 'The note to the buyer is too long.');
    }

    public static function validityInThePast(): self
    {
        return new self(self::VALIDITY_IN_THE_PAST, 'A quote cannot be valid until a day that is already past.');
    }

    public static function nothingToSend(): self
    {
        return new self(self::NOTHING_TO_SEND, 'There is no revision waiting to be sent.');
    }

    public static function noLines(): self
    {
        return new self(self::NO_LINES, 'A quote cannot be sent without a line.');
    }

    public static function discountAboveTotal(): self
    {
        return new self(self::DISCOUNT_ABOVE_TOTAL, 'The discount cannot be higher than the total of the lines.');
    }

    public static function lineUnavailable(): self
    {
        return new self(self::LINE_UNAVAILABLE, 'A line of the quote names a product that is no longer in the catalog.');
    }

    public static function validityNotExtended(): self
    {
        return new self(self::VALIDITY_NOT_EXTENDED, 'A quote can only be extended to a day after its current last day of validity.');
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
