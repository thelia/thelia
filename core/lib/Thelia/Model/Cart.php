<?php

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
     * @param string $token
     *
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return Cart|bool
     */
    public function duplicate(
        $token,
        Customer $customer = null,
        Currency $currency = null,
        EventDispatcherInterface $dispatcher = null
    ) {
        if (!$dispatcher) {
            return false;
        }

        $cartItems = $this->getCartItems();

        $cart = new self();
        $cart->setAddressDeliveryId($this->getAddressDeliveryId());
        $cart->setAddressInvoiceId($this->getAddressInvoiceId());
        $cart->setToken($token);
        $discount = 0;

        if (null === $currency) {
            $currencyQuery = CurrencyQuery::create();
            $currency = $currencyQuery->findPk($this->getCurrencyId()) ?: $currencyQuery->findOneByByDefault(1);
        }

        $cart->setCurrency($currency);

        if ($customer) {
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
                && (int) $product->getVisible() === 1
                && ($productSaleElements->getQuantity() >= $cartItem->getQuantity() || $product->getVirtual() === 1 || !ConfigQuery::checkAvailableStock())
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
     *
     * @return CartItem
     */
    public function getLastCartItemAdded()
    {
        return CartItemQuery::create()
            ->filterByCartId($this->getId())
            ->orderByCreatedAt(Criteria::DESC)
            ->findOne()
        ;
    }

    /**
     * Retrieve the total taxed amount.
     *
     * By default, the total include the discount
     *
     * /!\ The postage amount is not available so it's the total with or without discount an without postage
     *
     * @param bool $withDiscount
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return float
     */
    public function getTaxedAmount(Country $country, $withDiscount = true, State $state = null)
    {
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

        return round($total, 2);
    }

    /**
     * @param bool $withDiscount
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return float
     *
     * @see getTaxedAmount same as this method but the amount is without taxes
     */
    public function getTotalAmount($withDiscount = true, Country $country = null, State $state = null)
    {
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

        return round($total, 2);
    }

    /**
     * Return the VAT of all items.
     *
     * @param Country $taxCountry
     * @param null    $taxState
     * @param bool    $withDiscount
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return float|int|string
     */
    public function getTotalVAT($taxCountry, $taxState = null, $withDiscount = true)
    {
        return $this->getTaxedAmount($taxCountry, $withDiscount, $taxState) - $this->getTotalAmount($withDiscount, $taxCountry, $taxState);
    }

    /**
     * @param null $taxState
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return float
     */
    public function getDiscountVAT($taxCountry, $taxState = null)
    {
        return $this->getDiscount(true, $taxCountry, $taxState) - $this->getDiscount(false, $taxCountry, $taxState);
    }

    /**
     * Retrieve the total weight for all products in cart.
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return float
     */
    public function getWeight()
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
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return bool
     */
    public function isVirtual()
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

    /**
     * @param string $discount
     *
     * @return BaseCart|Cart
     */
    public function setDiscount($discount)
    {
        return parent::setDiscount(round($discount, 2));
    }

    /**
     * @param bool $withTaxes
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return float|int|string
     */
    public function getDiscount($withTaxes = true, Country $country = null, State $state = null)
    {
        if ($withTaxes || null === $country) {
            return parent::getDiscount();
        }

        return round(Calculator::getUntaxedCartDiscount($this, $country, $state), 2);
    }
}
