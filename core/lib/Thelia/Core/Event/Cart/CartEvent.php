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

namespace Thelia\Core\Event\Cart;

use Thelia\Core\Event\ActionEvent;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;

class CartEvent extends ActionEvent
{
    protected ?float $quantity = null;
    protected ?bool $append = null;
    protected ?bool $newness = null;
    protected ?int $productSaleElementsId = null;
    protected ?int $productId = null;
    protected ?CartItem $cartItem = null;
    protected ?int $cartItemId = null;
    protected ?BundleComposition $bundleComposition = null;
    protected ?float $bundleAvailability = null;
    protected bool $offeredByAPromotion = false;

    public function __construct(
        protected Cart $cart,
    ) {
    }

    public function setAppend(bool|int $append): static
    {
        $this->append = (bool) $append;

        return $this;
    }

    public function getAppend(): ?bool
    {
        return $this->append;
    }

    public function setCartItem(?CartItem $cartItem): static
    {
        $this->cartItem = $cartItem;

        return $this;
    }

    /**
     * Clear the current cart item.
     */
    public function clearCartItem(): static
    {
        $this->cartItem = null;

        return $this;
    }

    public function getCartItem(): ?CartItem
    {
        return $this->cartItem;
    }

    public function getCartItemId(): ?int
    {
        return $this->cartItemId;
    }

    /**
     * @return $this
     */
    public function setCartItemId(?int $cartItemId): static
    {
        $this->cartItemId = $cartItemId;

        return $this;
    }

    public function setNewness(int|bool $newness): static
    {
        $this->newness = (bool) $newness;

        return $this;
    }

    public function getNewness(): ?bool
    {
        return $this->newness;
    }

    /**
     * @param int $productId the product ID
     */
    public function setProductId(int|string $productId): static
    {
        $this->productId = (int) $productId;

        return $this;
    }

    /**
     * @return int the product ID
     */
    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function setProductSaleElementsId(int|string $productSaleElementsId): static
    {
        $this->productSaleElementsId = (int) $productSaleElementsId;

        return $this;
    }

    public function getProductSaleElementsId(): ?int
    {
        return $this->productSaleElementsId;
    }

    /**
     * A combination sold by measure takes a quantity with decimals (1.25 kg): it is
     * carried as it is, the rules of the combination deciding whether the cart takes it.
     */
    public function setQuantity(int|float $quantity): static
    {
        $this->quantity = (float) $quantity;

        return $this;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    /**
     * The components the customer chose for a bundle: null for the default composition, and for a
     * product that is not a bundle. The guard on CART_ADDITEM replaces it with the composition the
     * bundle will hold.
     */
    public function setBundleComposition(?BundleComposition $bundleComposition): static
    {
        $this->bundleComposition = $bundleComposition;

        return $this;
    }

    /**
     * The line is a gift a promotion adds (FreeProduct): it is not sold, so the cart
     * holds it to none of the quantity rules of its combination.
     */
    public function setOfferedByAPromotion(bool $offeredByAPromotion): static
    {
        $this->offeredByAPromotion = $offeredByAPromotion;

        return $this;
    }

    public function getBundleComposition(): ?BundleComposition
    {
        return $this->bundleComposition;
    }

    /**
     * How many bundles of the composition the components can supply, once the guard on
     * CART_ADDITEM has asked: the cart reads it instead of asking again. Null when nobody asked.
     */
    public function setBundleAvailability(?float $bundleAvailability): static
    {
        $this->bundleAvailability = $bundleAvailability;

        return $this;
    }

    public function getBundleAvailability(): ?float
    {
        return $this->bundleAvailability;
    }

    public function isOfferedByAPromotion(): bool
    {
        return $this->offeredByAPromotion;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}
