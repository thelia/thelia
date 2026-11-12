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

namespace Thelia\Domain\Pricing\CustomerPrice;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What one sale element sells at for a given customer, once the price of the
 * group, the promotions and the two discounts (the group one, then the
 * customer one) have all been applied - or the price of a price list, which
 * nothing is applied to.
 *
 * Untaxed and not rounded: the line of a cart rounds once when it is written,
 * and a screen rounds once when it displays.
 */
#[Exclude]
final readonly class ResolvedPrice
{
    /** The catalog price and promo price, less the discount of the group. */
    public const SOURCE_BASE = 'base';

    /** A price the group has of its own. */
    public const SOURCE_CUSTOMER_GROUP = 'customer_group';

    /** A price list line, final: nothing else applies to it. */
    public const SOURCE_PRICE_LIST = 'price_list';

    public function __construct(
        /** The untaxed price retained, discounts included, not rounded. */
        public float $untaxedPrice,
        /** The same for the promotional price. */
        public float $untaxedPromoPrice,
        public bool $isPromo,
        public bool $displayInitialPrice,
        /** One of the `EffectivePrice::SOURCE_*` constants, or self::SOURCE_BASE or self::SOURCE_CUSTOMER_GROUP. */
        public string $source,
        public int $customerGroupId,
        /** The discount rate of the group, as a percentage between 0 and 100. */
        public float $customerGroupDiscountRate,
        /** The discount rate of the customer, as a percentage between 0 and 100. */
        public float $customerDiscountRate,
        /** False when an exclusive price list does not price the sale element: it cannot be bought. */
        public bool $purchasable = true,
        /** The price list the price comes from, or the exclusive one that closes the sale element. */
        public ?int $priceListId = null,
        /**
         * The tiers of the price list that priced the sale element, the smallest minimum quantity first.
         *
         * @var array<int, array{minQuantity: int, untaxedPrice: string}>
         */
        public array $tiers = [],
    ) {
    }

    /**
     * The same price, for a sale element an exclusive price list does not price: shown as the
     * catalog chain prices it, never bought.
     */
    public function closedBy(int $priceListId): self
    {
        return new self(
            $this->untaxedPrice,
            $this->untaxedPromoPrice,
            $this->isPromo,
            $this->displayInitialPrice,
            $this->source,
            $this->customerGroupId,
            $this->customerGroupDiscountRate,
            $this->customerDiscountRate,
            false,
            $priceListId,
        );
    }

    /**
     * The price list whose line gave this price, null when the catalog chain priced it (the
     * exclusive list that only closes a sale element does not price it).
     */
    public function pricedByPriceListId(): ?int
    {
        return self::SOURCE_PRICE_LIST === $this->source ? $this->priceListId : null;
    }

    /**
     * What is charged: the promo price while the sale element is on promotion, the
     * price otherwise.
     */
    public function chargedPrice(): float
    {
        return $this->isPromo ? $this->untaxedPromoPrice : $this->untaxedPrice;
    }
}
