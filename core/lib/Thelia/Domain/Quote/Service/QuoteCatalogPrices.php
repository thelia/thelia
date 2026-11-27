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

namespace Thelia\Domain\Quote\Service;

use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContextInterface;
use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * What a buyer would pay for a sale element today, the price a quote line is set against.
 *
 * It is the answer of the customer price context, the one the cart prices its lines with: the
 * groups, the price lists and the promotions of the buyer all apply. Read only.
 */
final readonly class QuoteCatalogPrices
{
    public function __construct(private CustomerPriceContextInterface $customerPriceContext)
    {
    }

    /**
     * @param array<int, float> $quantities quantity wanted by sale element id: the tier of a price list depends on it
     *
     * @return array<int, string> unit price without taxes with six decimals, by sale element id; a sale element with no price is absent
     */
    public function resolve(array $quantities, Currency $currency, ?Customer $customer): array
    {
        if ([] === $quantities) {
            return [];
        }

        $resolved = $this->customerPriceContext->resolve(
            array_keys($quantities),
            $currency,
            $customer,
            array_map(static fn (float $quantity): int => (int) floor($quantity), $quantities),
        );

        $prices = [];

        foreach ($resolved as $saleElementId => $price) {
            $prices[(int) $saleElementId] = number_format(round($price->chargedPrice(), 6), 6, '.', '');
        }

        return $prices;
    }
}
