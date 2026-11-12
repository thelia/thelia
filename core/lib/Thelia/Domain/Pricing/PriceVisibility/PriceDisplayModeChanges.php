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

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;

/**
 * The products whose price display mode a write in progress changes: the column is flagged
 * modified before the write and no longer after it, so a listener keeps the product between the
 * two events of the write and asks, after it, whether the mode was what changed.
 *
 * Weakly held, so that a write that fails between the two events leaves nothing behind in a
 * long-running worker. Not a service: each listener that purges on a mode change owns its own,
 * since asking forgets the product and a shared one would answer only the first to ask.
 */
#[Exclude]
final class PriceDisplayModeChanges
{
    /** @var \WeakMap<Product, true> */
    private \WeakMap $products;

    public function __construct()
    {
        $this->products = new \WeakMap();
    }

    /**
     * To be called before the write.
     */
    public function remember(Product $product): void
    {
        if ($product->isColumnModified(ProductTableMap::COL_PRICE_DISPLAY_MODE)) {
            $this->products[$product] = true;
        }
    }

    /**
     * To be called after the write: whether the mode was written, once for each time it was
     * remembered.
     */
    public function wereWritten(Product $product): bool
    {
        if (!isset($this->products[$product])) {
            return false;
        }

        unset($this->products[$product]);

        return true;
    }
}
