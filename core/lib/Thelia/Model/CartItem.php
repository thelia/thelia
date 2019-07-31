<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cart\CartItemEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\CartItem as BaseCartItem;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\TaxEngine\Calculator;

class CartItem extends BaseCartItem
{
    /** @var EventDispatcherInterface */
    protected $dispatcher;

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function setDisptacher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param ConnectionInterface|null $con
     * @return bool
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        if ($this->dispatcher) {
            $cartItemEvent = new CartItemEvent($this);
            $this->dispatcher->dispatch(TheliaEvents::CART_ITEM_CREATE_BEFORE, $cartItemEvent);
        }
        return true;
    }

    /**
     * @param ConnectionInterface|null $con
     * @return bool
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        parent::preUpdate($con);

        if ($this->dispatcher) {
            $cartItemEvent = new CartItemEvent($this);
            $this->dispatcher->dispatch(TheliaEvents::CART_ITEM_UPDATE_BEFORE, $cartItemEvent);
        }
        return true;
    }

    /**
     * @param ConnectionInterface|null $con
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        parent::postInsert($con);

        if ($this->dispatcher) {
            $cartEvent = new CartEvent($this->getCart());

            $this->dispatcher->dispatch(TheliaEvents::AFTER_CARTADDITEM, $cartEvent);
        }
    }

    /**
     * @param ConnectionInterface|null $con
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        parent::postUpdate($con);

        if ($this->dispatcher) {
            $cartEvent = new CartEvent($this->getCart());

            $this->dispatcher->dispatch(TheliaEvents::AFTER_CARTUPDATEITEM, $cartEvent);
        }
    }

    /**
     * @param $value
     * @return $this
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
     * @return $this
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

    public function getRealPrice()
    {
        return $this->getPromo() == 1 ? $this->getPromoPrice() : $this->getPrice();
    }

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
     * @param Country $country
     * @param State|null $state
     * @param bool $withDiscount
     * @return float
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getRealTaxedPrice(Country $country, State $state = null, $withDiscount = false)
    {
        return $this->getPromo() == 1 ? $this->getTaxedPromoPrice($country, $state) : $this->getTaxedPrice($country, $state);
    }

    /**
     * @param Country $country
     * @param State|null $state
     * @param bool $withDiscount
     * @return float
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getTaxedPrice(Country $country, State $state = null, $withDiscount = false)
    {
        $taxCalculator = $this->createCalculator($country, $state, $withDiscount);

        return $taxCalculator->load($this->getProduct(), $country, $state)->getTaxedPrice($this->getPrice());
    }

    /**
     * @param Country $country
     * @param State|null $state
     * @param bool $withDiscount
     * @return float
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getTaxedPromoPrice(Country $country, State $state = null, $withDiscount = false)
    {
        $taxCalculator = $this->createCalculator($country, $state, $withDiscount);

        return $taxCalculator->load($this->getProduct(), $country, $state)->getTaxedPrice($this->getPromoPrice());
    }

    /**
     * @param Country $country
     * @param State|null $state
     * @param bool $withDiscount
     * @return float
     * @throws \Propel\Runtime\Exception\PropelException
     * @since Version 2.3
     */
    public function getTotalRealTaxedPrice(Country $country, State $state = null, $withDiscount = false)
    {
        return $this->getPromo() == 1 ? $this->getTotalTaxedPromoPrice($country, $state, $withDiscount) : $this->getTotalTaxedPrice($country, $state, $withDiscount);
    }

    /**
     * @since Version 2.3
     * @param Country $country
     * @param State|null $state
     * @param bool $withDiscount
     * @return float
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getTotalTaxedPrice(Country $country, State $state = null, $withDiscount = false)
    {
        $taxCalculator = $this->createCalculator($country, $state, $withDiscount);

        return $taxCalculator->load($this->getProduct(), $country, $state)->getTaxedPrice($this->getPrice()*$this->getQuantity());
    }

    /**
     * @since Version 2.3
     * @param Country $country
     * @param State|null $state
     * @param bool $withDiscount
     * @return float
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getTotalTaxedPromoPrice(Country $country, State $state = null, $withDiscount = false)
    {
        $taxCalculator = $this->createCalculator($country, $state, $withDiscount);

        return $taxCalculator->load($this->getProduct(), $country, $state)->getTaxedPrice($this->getPromoPrice()*$this->getQuantity());
    }

    /**
     * @param bool $withDiscount
     * @return Calculator
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function createCalculator($country, $state, $withDiscount)
    {
        return $withDiscount ? Calculator::createFromCart($this->getCart(), $country, $state) : new Calculator();
    }
}
