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
 * A line of the file as it was read: raw cells, trimmed, an empty cell being null. Nothing is
 * validated yet.
 */
final readonly class PriceListCsvRow
{
    public function __construct(
        public int $line,
        public ?string $saleElementReference,
        public ?string $saleElementId,
        public ?string $minimumQuantity,
        public ?string $price,
        public ?string $discountPercent,
        public ?PriceListImportLineError $error = null,
    ) {
    }
}
