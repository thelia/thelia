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

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartItemEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\CartItem as BaseCartItem;
use Thelia\TaxEngine\Calculator;

class CartItem extends BaseCartItem
{
    /** @var EventDispatcherInterface */
    protected $dispatcher;

    public function setDisptacher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @return bool
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        if ($this->dispatcher) {
            $cartItemEvent = new CartItemEvent($this);
            $this->dispatcher->dispatch($cartItemEvent, TheliaEvents::CART_ITEM_CREATE_BEFORE);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        parent::preUpdate($con);

        if ($this->dispatcher) {
            $cartItemEvent = new CartItemEvent($this);
            $this->dispatcher->dispatch($cartItemEvent, TheliaEvents::CART_ITEM_UPDATE_BEFORE);
        }

        return true;
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postInsert(ConnectionInterface $con = null): void
    {
        parent::postInsert($con);

        if ($this->dispatcher) {
            $cartEvent = new CartEvent($this->getCart());

            $this->dispatcher->dispatch($cartEvent, TheliaEvents::AFTER_CARTADDITEM);
        }
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postUpdate(ConnectionInterface $con = null): void
    {
        parent::postUpdate($con);

        if ($this->dispatcher) {
            $cartEvent = new CartEvent($this->getCart());

            $this->dispatcher->dispatch($cartEvent, TheliaEvents::AFTER_CARTUPDATEITEM);
        }
    }

    /**
     * @param $value
     *
     * @return $this
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function updateQuantity($value)
    {
        $currentQuantity = $this->getQuantity();

        if ($value <= 0) {
            $value = $currentQuantity;
        }

        if (ConfigQuery::checkAvailableStock()) {
            $productSaleElements = $this->getProductSaleElements();
            $product = $productSaleElements->getProduct();

            if ($product->getVirtual() === 0) {
                if ($productSaleElements->getQuantity() < $value) {
                    $value = $currentQuantity;
                }
            }
        }

        $this->setQuantity($value);

        return $this;
    }

    /**
     * @param $value
     *
     * @return $this
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function addQuantity($value)
    {
        $currentQuantity = $this->getQuantity();
        $newQuantity = $currentQuantity + $value;

        if (ConfigQuery::checkAvailableStock()) {
            $productSaleElements = $this->getProductSaleElements();
            $product = $productSaleElements->getProduct();

            if ($product->getVirtual() === 0) {
                if ($productSaleElements->getQuantity() < $newQuantity) {
                    $newQuantity = $currentQuantity;
                }
            }
        }

        $this->setQuantity($newQuantity);

        return $this;
    }

    /**
     * @return float
     */
    public function getRealPrice()
    {
        return (float) ((int) $this->getPromo() === 1 ? $this->getPromoPrice() : $this->getPrice());
    }

    /**
     * @param null $locale
     *
     * @return Product
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getProduct(ConnectionInterface $con = null, $locale = null)
    {
        $product = parent::getProduct($con);

        $translation = $product->getTranslation($locale);

        if ($translation->isNew()) {
            if (ConfigQuery::getDefaultLangWhenNoTranslationAvailable() == Lang::REPLACE_BY_DEFAULT_LANGUAGE) {
                $locale = Lang::getDefaultLanguage()->getLocale();
            }
        }

        $product->setLocale($locale);

        return $product;
    }

    /**
     * @return float
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getRealTaxedPrice(Country $country, State $state = null)
    {
        return (int) $this->getPromo() === 1 ? $this->getTaxedPromoPrice($country, $state) : $this->getTaxedPrice($country, $state);
    }

    /**
     * @return float
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getTaxedPrice(Country $country, State $state = null)
    {
        $taxCalculator = new Calculator();

        return $taxCalculator->load($this->getProduct(), $country, $state)->getTaxedPrice($this->getPrice());
    }

    /**
     * @return float
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getTaxedPromoPrice(Country $country, State $state = null)
    {
        $taxCalculator = new Calculator();

        return $taxCalculator->load($this->getProduct(), $country, $state)->getTaxedPrice($this->getPromoPrice());
    }

    /**
     * @since Version 2.3
     *
     * @return float
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getTotalRealTaxedPrice(Country $country, State $state = null)
    {
        return (int) $this->getPromo() === 1 ? $this->getTotalTaxedPromoPrice($country, $state) : $this->getTotalTaxedPrice($country, $state);
    }

    /**
     * @since Version 2.3
     *
     * @return float
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getTotalTaxedPrice(Country $country, State $state = null)
    {
        return round($this->getTaxedPrice($country, $state) * $this->getQuantity(), 2);
    }

    /**
     * @since Version 2.3
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getTotalTaxedPromoPrice(Country $country, State $state = null)
    {
        return round($this->getTaxedPromoPrice($country, $state) * $this->getQuantity(), 2);
    }

    /**
     * @since Version 2.4
     *
     * @return float
     */
    public function getTotalPrice()
    {
        return round($this->getPrice() * $this->getQuantity(), 2);
    }

    /**
     * @since Version 2.4
     *
     * @return float
     */
    public function getTotalPromoPrice()
    {
        return round($this->getPromoPrice() * $this->getQuantity(), 2);
    }

    /**
     * @since Version 2.4
     *
     * @return float
     */
    public function getTotalRealPrice()
    {
        return round($this->getRealPrice() * $this->getQuantity(), 2);
    }
}
