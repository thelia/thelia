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
 * A component added to, changed in or taken out of a bundle (PRODUCT_BUNDLE_ITEM_ADD,
 * _UPDATE, _DELETE). The listener writes through BundleCompositionService, which refuses
 * what a bundle cannot hold, and sets the item it wrote on the event; a delete leaves it null.
 *
 * An add names the bundle; an update or a delete names the item.
 */
class ProductBundleItemEvent extends ActionEvent
{
    private ?ProductBundleItem $item = null;

    public function __construct(
        private readonly ?int $bundleProductId,
        private readonly ?int $bundleItemId,
        private readonly ?int $productSaleElementsId = null,
        private readonly string $quantity = '1',
        private readonly ?string $minQuantity = null,
        private readonly ?string $maxQuantity = null,
        private readonly bool $required = true,
        private readonly ?int $position = null,
    ) {
    }

    public static function add(int $bundleProductId, int $productSaleElementsId, string $quantity = '1', ?string $minQuantity = null, ?string $maxQuantity = null, bool $required = true, ?int $position = null): self
    {
        return new self($bundleProductId, null, $productSaleElementsId, $quantity, $minQuantity, $maxQuantity, $required, $position);
    }

    public static function update(int $bundleItemId, int $productSaleElementsId, string $quantity, ?string $minQuantity, ?string $maxQuantity, bool $required): self
    {
        return new self(null, $bundleItemId, $productSaleElementsId, $quantity, $minQuantity, $maxQuantity, $required);
    }

    public static function delete(int $bundleItemId): self
    {
        return new self(null, $bundleItemId);
    }

    public function getBundleProductId(): ?int
    {
        return $this->bundleProductId;
    }

    public function getBundleItemId(): ?int
    {
        return $this->bundleItemId;
    }

    public function getProductSaleElementsId(): ?int
    {
        return $this->productSaleElementsId;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function getMinQuantity(): ?string
    {
        return $this->minQuantity;
    }

    public function getMaxQuantity(): ?string
    {
        return $this->maxQuantity;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getPosition(): ?int
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
