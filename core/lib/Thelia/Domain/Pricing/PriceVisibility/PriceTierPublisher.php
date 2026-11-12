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

namespace Thelia\Domain\Pricing\PriceVisibility;

use Thelia\Domain\Catalog\Product\ProductTaxedPriceResolver;
use Thelia\Domain\Pricing\PriceList\PriceListResolverInterface;
use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * The tiers of the price list that prices a sale element for a customer, in the shape the front
 * publishes them (`price`, `promoPrice` untaxed, `taxedPrice`, `taxedPromoPrice` with the taxes):
 * the readers other than the API resource, such as the data access of a product page, ask it
 * and compute nothing of their own.
 *
 * A price list line is final, so no promotion applies to it: the promotional price is the price.
 */
final readonly class PriceTierPublisher
{
    public function __construct(
        private PriceListResolverInterface $priceListResolver,
        private ProductTaxedPriceResolver $productTaxedPriceResolver,
    ) {
    }

    /**
     * @param list<int> $productSaleElementsIds the sale elements of one product; with no product id the taxed prices are null
     *
     * @return array<int, list<array{minQuantity: int, price: float, promoPrice: float, taxedPrice: float|null, taxedPromoPrice: float|null}>> indexed by sale element id; absent means no tier
     */
    public function forProduct(?int $productId, array $productSaleElementsIds, Currency $currency, ?Customer $customer): array
    {
        if ([] === $productSaleElementsIds) {
            return [];
        }

        $published = [];

        foreach ($this->priceListResolver->tiersFor($productSaleElementsIds, $currency, $customer) as $productSaleElementsId => $tiers) {
            foreach ($tiers as $tier) {
                $price = (float) $tier['untaxedPrice'];
                $taxedPrice = null === $productId ? null : $this->productTaxedPriceResolver->taxedPrice($productId, $price);

                $published[$productSaleElementsId][] = [
                    'minQuantity' => $tier['minQuantity'],
                    'price' => $price,
                    'promoPrice' => $price,
                    'taxedPrice' => $taxedPrice,
                    'taxedPromoPrice' => $taxedPrice,
                ];
            }
        }

        return $published;
    }
}
