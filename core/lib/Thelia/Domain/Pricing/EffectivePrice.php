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
 *
 * A bundle priced by its components is the one source that also says the price: its
 * catalog price is not the price of anything, so `untaxedPrice` replaces it, and the
 * promo price is the price less the discount of the bundle - a promotion only when
 * there is one. Every reader merges the two through settle().
 */
#[Exclude]
final readonly class EffectivePrice
{
    public const SOURCE_RULE = 'rule';

    public const SOURCE_RESERVED_SALE = 'reserved_sale';

    public const SOURCE_SALE_UNIT = 'sale_unit';

    public const SOURCE_BUNDLE = 'bundle';

    public function __construct(
        public int $productSaleElementsId,
        public float $untaxedPromoPrice,
        public bool $displayInitialPrice,
        public string $source,
        public ?int $ruleId = null,
        public ?int $saleId = null,
        public ?\DateTimeInterface $endsAt = null,
        public ?int $saleUnitId = null,
        public ?string $untaxedPrice = null,
    ) {
    }

    /**
     * Whether the sale element is on promotion: always, but for a bundle without discount,
     * which is sold at its price.
     */
    public function isPromo(): bool
    {
        return null === $this->untaxedPrice || $this->untaxedPromoPrice < (float) $this->untaxedPrice;
    }

    /**
     * The one merge of the catalog price and the effective price, for every reader.
     *
     * The catalog price stands unless this price says its own. A customer discount rate
     * (percent) is applied to the values that come from here, never to the catalog price
     * handed in, which the caller has already read with its own discount: a reader that
     * applies the discount afterwards passes none.
     */
    public function settle(float $catalogUntaxedPrice, float $customerDiscount = 0.0): SettledPrice
    {
        $factor = $customerDiscount > 0 ? 1 - $customerDiscount / 100 : 1.0;
        $priceReplaced = null !== $this->untaxedPrice;

        return new SettledPrice(
            $priceReplaced ? (float) $this->untaxedPrice * $factor : $catalogUntaxedPrice,
            $this->untaxedPromoPrice * $factor,
            $this->isPromo(),
            $this->displayInitialPrice,
            $priceReplaced,
        );
    }
}
