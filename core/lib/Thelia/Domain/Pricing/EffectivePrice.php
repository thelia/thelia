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

namespace Thelia\Domain\Pricing;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The promo price a visitor is actually shown for one sale element, once every
 * mechanism that prices without writing the catalog has spoken: the catalog price
 * rules, then the reserved operations. Absent when none of them has anything to say
 * - the catalog's own promo price then stands.
 *
 * Untaxed and before the customer discount, like `product_price.promo_price`, which
 * is what the readers substitute it for.
 */
#[Exclude]
final readonly class EffectivePrice
{
    public const SOURCE_RULE = 'rule';

    public const SOURCE_RESERVED_SALE = 'reserved_sale';

    public function __construct(
        public int $productSaleElementsId,
        public float $untaxedPromoPrice,
        public bool $displayInitialPrice,
        public string $source,
        public ?int $ruleId = null,
        public ?int $saleId = null,
        public ?\DateTimeInterface $endsAt = null,
    ) {
    }
}
