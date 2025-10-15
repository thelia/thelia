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

namespace Thelia\Domain\Order\Service;

use Propel\Runtime\Exception\PropelException;
use Thelia\Model\OrderProductTax;
use Thelia\Model\Product;

readonly class TaxProvider
{
    /**
     * @return iterable<OrderProductTax>
     *
     * @throws PropelException
     */
    public function computeTaxesForCartItem(
        Product $product,
        $country,
        float $price,
        ?float $promoPrice,
        string $locale,
    ): ?iterable {
        return $product->getTaxRule()?->getTaxDetail(
            $product,
            $country,
            $price,
            $promoPrice,
            $locale
        );
    }
}
