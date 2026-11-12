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

namespace Thelia\Domain\Pricing\PriceList\Import;

/**
 * A line of the file that is not written, and why. The line number is the one of the file, the
 * header being line 1. The reason is one of the constants below, stable for a screen to translate;
 * the detail completes it when it has something to say (the line a duplicate repeats, the value
 * that was refused).
 */
final readonly class PriceListImportLineError
{
    public const string WRONG_COLUMN_COUNT = 'wrong_column_count';
    public const string INVALID_ENCODING = 'invalid_encoding';
    public const string NO_SALE_ELEMENT = 'no_sale_element';
    public const string UNKNOWN_REFERENCE = 'unknown_reference';
    public const string AMBIGUOUS_REFERENCE = 'ambiguous_reference';
    public const string UNKNOWN_SALE_ELEMENT_ID = 'unknown_sale_element_id';
    public const string REFERENCE_AND_ID_DISAGREE = 'reference_and_id_disagree';
    public const string INVALID_MINIMUM_QUANTITY = 'invalid_minimum_quantity';
    public const string TWO_AMOUNTS = 'two_amounts';
    public const string NO_AMOUNT = 'no_amount';
    public const string INVALID_PRICE = 'invalid_price';
    public const string INVALID_DISCOUNT_PERCENT = 'invalid_discount_percent';
    public const string DUPLICATE_IN_FILE = 'duplicate_in_file';

    public function __construct(
        public int $line,
        public string $reason,
        public ?string $detail = null,
    ) {
    }
}
