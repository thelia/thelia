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

namespace Thelia\Domain\Pricing\Rule\Storage;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The catalog price of one sale element in every currency of the shop, as
 * `product_price` gives it: the row of the currency when the shopkeeper typed one,
 * the default currency row converted at the rate otherwise.
 */
#[Exclude]
final readonly class BasePrices
{
    /**
     * @param array<int, float> $untaxedPriceByCurrencyId
     */
    public function __construct(
        public int $productSaleElementsId,
        public int $productId,
        public array $untaxedPriceByCurrencyId,
    ) {
    }

    public function in(int $currencyId): ?float
    {
        return $this->untaxedPriceByCurrencyId[$currencyId] ?? null;
    }
}
