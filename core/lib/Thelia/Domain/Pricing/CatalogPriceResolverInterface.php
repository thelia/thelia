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

use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * What the catalog price rules make of a batch of sale elements, for one visitor.
 *
 * Every reader of a price - the product loops, the front API, the product page
 * service and the cart - asks this once for the sale elements it is about to show
 * and substitutes the answer for the promo price. A module that prices products by
 * rules of its own decorates the service: it answers for the sale elements it
 * covers and hands the rest to the decorated resolver.
 */
interface CatalogPriceResolverInterface
{
    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, ResolvedCatalogPrice> keyed by sale element id; a sale element no
     *                                          rule prices is absent, its catalog price stands
     */
    public function resolve(
        array $productSaleElementsIds,
        Currency $currency,
        ?Customer $customer,
        ?\DateTimeInterface $now = null,
    ): array;
}
