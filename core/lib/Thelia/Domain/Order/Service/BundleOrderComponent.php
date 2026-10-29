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

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;

/**
 * One component of a pack cart line, as the order will take it from the stock.
 */
#[Exclude]
final readonly class BundleOrderComponent
{
    /**
     * @param float $quantity the quantity of the component in the pack times the quantity of the pack
     */
    public function __construct(
        public Product $product,
        public ProductSaleElements $productSaleElements,
        public float $quantity,
    ) {
    }
}
