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

namespace Thelia\Domain\Catalog\Product\Identifier;

/**
 * The codes a merchant feed or a marketplace asks for one combination.
 *
 * The manufacturer brand is already resolved: the brand set on the combination, or the
 * brand of its product when it has none.
 */
final readonly class ProductIdentifiers
{
    public function __construct(
        public int $productSaleElementsId,
        public ?string $gtin,
        public ?string $mpn,
        public ?int $manufacturerBrandId,
    ) {
    }
}
