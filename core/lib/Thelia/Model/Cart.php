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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cart\CartDuplicationEvent;
use Thelia\Core\Event\Cart\CartItemDuplicationItem;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Cart as BaseCart;
use Thelia\TaxEngine\Calculator;

class Cart extends BaseCart
{
    /**
     * Duplicate the current existing cart. Only the token is changed.
     *
     * @throws \Exception
     * @throws PropelException
     */
    public function duplicate(
        string $token,
        ?Customer $customer = null,
        ?Currency $currency = null,
        ?EventDispatcherInterface $dispatcher = null,
    ): self|bool {
        if (!$dispatcher instanceof EventDispatcherInterface) {
            return false;
        }

        $cartItems = $this->getCartItems();

        $cart = new self();
        $cart->setAddressDeliveryId($this->getAddressDeliveryId());
        $cart->setAddressInvoiceId($this->getAddressInvoiceId());
        $cart->setToken($token);

        $discount = 0;

        if (!$currency instanceof Currency) {
            $currencyQuery = CurrencyQuery::create();
            $currency = $currencyQuery->findPk($this->getCurrencyId()) ?: $currencyQuery->findOneByByDefault(1);
        }

        $cart->setCurrency($currency);

        if ($customer instanceof Customer) {
            $cart->setCustomer($customer);

            if ($customer->getDiscount() > 0) {
                $discount = $customer->getDiscount();
            }
        }

        $cart->save();

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProduct();
            $productSaleElements = $cartItem->getProductSaleElements();

            if ($product
                && $productSaleElements
                && 1 === (int) $product->getVisible()
                && ($productSaleElements->getQuantity() >= $cartItem->getQuantity() || 1 === $product->getVirtual() || !ConfigQuery::checkAvailableStock())
            ) {
                $item = new CartItem();
                $item->setCart($cart);
                $item->setProductId($cartItem->getProductId());
                $item->setQuantity($cartItem->getQuantity());
                $item->setProductSaleElements($productSaleElements);
                $prices = $productSaleElements->getPricesByCurrency($currency, $discount);
                $item
                    ->setPrice($prices->getPrice())
                    ->setPromoPrice($prices->getPromoPrice())
                    ->setPromo($productSaleElements->getPromo());

                $item->save();
                $dispatcher->dispatch(new CartItemDuplicationItem($item, $cartItem), TheliaEvents::CART_ITEM_DUPLICATE);
            }
        }

        // Dispatche the duplication event before delting the cart from the database,
        $dispatcher->dispatch(new CartDuplicationEvent($cart, $this), TheliaEvents::CART_DUPLICATED);

        try {
            $this->delete();
        } catch (\Exception) {
            // just fail silently in some cases
        }

        return $cart;
    }

    /**
     * Retrieve the last item added in the cart.
     */
    public function getLastCartItemAdded(): CartItem
    {
        return CartItemQuery::create()
            ->filterByCartId($this->getId())
            ->orderByCreatedAt(Criteria::DESC)
            ->findOne();
    }

    /**
     * Retrieve the total taxed amount.
     *
     * By default, the total include the discount
     *
     * /!\ The postage amount is not available so it's the total with or without discount an without postage
     *
     * @throws PropelException
     */
    public function getTaxedAmount(
        Country $country,
        bool $withDiscount = true,
        ?State $state = null,
        bool $withPostage = false,
    ): float {
        $total = 0;

        foreach ($this->getCartItems() as $cartItem) {
            $total += $cartItem->getTotalRealTaxedPrice($country, $state);
        }

        if ($withDiscount) {
            $total -= $this->getDiscount();

            if ($total < 0) {
                $total = 0;
            }
        }

        if ($withPostage) {
            $total += $this->getTaxedPostage();
        }

        return round($total, 2);
    }

    /**
     * @throws PropelException
     *
     * @see getTaxedAmount same as this method but the amount is without taxes
     */
    public function getTotalAmount(
        bool $withDiscount = true,
        ?Country $country = null,
        ?State $state = null,
        bool $withPostage = false,
    ): float {
        $total = 0;

        foreach ($this->getCartItems() as $cartItem) {
            $total += $cartItem->getTotalRealPrice();
        }

        if ($withDiscount) {
            $total -= $this->getDiscount(false, $country, $state);

            if ($total < 0) {
                $total = 0;
            }
        }

        if ($withPostage) {
            $total += (float) $this->getPostage();
        }

        return round($total, 2);
    }

    /**
     * Return the VAT of all items.
     *
     * @throws PropelException
     */
    public function getTotalVAT($taxCountry, $taxState = null, $withDiscount = true, $withPostage = false): float|int|string
    {
        return $this->getTaxedAmount($taxCountry, $withDiscount, $taxState, $withPostage) - $this->getTotalAmount($withDiscount, $taxCountry, $taxState, $withPostage);
    }

    /**
     * @throws PropelException
     */
    public function getDiscountVAT($taxCountry, $taxState = null): float
    {
        return $this->getDiscount(true, $taxCountry, $taxState) - $this->getDiscount(false, $taxCountry, $taxState);
    }

    /**
     * Retrieve the total weight for all products in cart.
     *
     * @throws PropelException
     */
    public function getWeight(): float
    {
        $weight = 0;

        foreach ($this->getCartItems() as $cartItem) {
            $itemWeight = $cartItem->getProductSaleElements()->getWeight();
            $itemWeight *= $cartItem->getQuantity();

            $weight += $itemWeight;
        }

        return $weight;
    }

    /**
     * Tell if the cart contains only virtual products.
     *
     * @throws PropelException
     */
    public function isVirtual(): bool
    {
        foreach ($this->getCartItems() as $cartItem) {
            if (0 < $cartItem->getProductSaleElements()->getWeight()) {
                return false;
            }

            $product = $cartItem->getProductSaleElements()->getProduct();

            if (!$product->getVirtual()) {
                return false;
            }
        }

        // An empty cart is not virtual.
        return $this->getCartItems()->count() > 0;
    }

    public function setDiscount($discount): self
    {
        return parent::setDiscount(round((float) $discount, 2));
    }

    /**
     * @throws PropelException
     */
    public function getDiscount(bool $withTaxes = true, ?Country $country = null, ?State $state = null): float|int|string
    {
        if ($withTaxes || !$country instanceof Country) {
            return parent::getDiscount();
        }

        return round(Calculator::getUntaxedCartDiscount($this, $country, $state), 2);
    }

    public function getTaxedPostage(): float
    {
        return (float) $this->getPostage() + (float) $this->getPostageTax();
    }
}
