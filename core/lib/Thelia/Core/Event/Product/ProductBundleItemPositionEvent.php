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

namespace Thelia\Core\Event\Product;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\ProductBundleItem;

/**
 * A component moved in the display order of its bundle (PRODUCT_BUNDLE_ITEM_UPDATE_POSITION).
 */
class ProductBundleItemPositionEvent extends ActionEvent
{
    private ?ProductBundleItem $item = null;

    public function __construct(
        private readonly int $bundleItemId,
        private readonly int $position,
    ) {
    }

    public function getBundleItemId(): int
    {
        return $this->bundleItemId;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getItem(): ?ProductBundleItem
    {
        return $this->item;
    }

    public function setItem(?ProductBundleItem $item): static
    {
        $this->item = $item;

        return $this;
    }
}
