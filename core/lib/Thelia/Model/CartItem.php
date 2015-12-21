<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\CartItem as BaseCartItem;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\TaxEngine\Calculator;

class CartItem extends BaseCartItem
{
    protected $dispatcher;

    public function setDisptacher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function postInsert(ConnectionInterface $con = null)
    {
        if ($this->dispatcher) {
            $cartEvent = new CartEvent($this->getCart());

            $this->dispatcher->dispatch(TheliaEvents::AFTER_CARTADDITEM, $cartEvent);
        }
    }

    public function postUpdate(ConnectionInterface $con = null)
    {
        if ($this->dispatcher) {
            $cartEvent = new CartEvent($this->getCart());

            $this->dispatcher->dispatch(TheliaEvents::AFTER_CARTUPDATEITEM, $cartEvent);
        }
    }

    /**
     * @param $value
     * @return $this
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
     * @return float
     */
    public function getRealTaxedPrice(Country $country)
    {
        return $this->getPromo() == 1 ? $this->getTaxedPromoPrice($country) : $this->getTaxedPrice($country);
    }

    /**
     * @param Country $country
     * @return float
     */
    public function getTaxedPrice(Country $country)
    {
        $taxCalculator = new Calculator();

        return $taxCalculator->load($this->getProduct(), $country)->getTaxedPrice($this->getPrice());
    }

    /**
     * @param Country $country
     * @return float
     */
    public function getTaxedPromoPrice(Country $country)
    {
        $taxCalculator = new Calculator();

        return $taxCalculator->load($this->getProduct(), $country)->getTaxedPrice($this->getPromoPrice());
    }

    /**
     * @since Version 2.3
     * @param Country $country
     * @return float
     */
    public function getTotalRealTaxedPrice(Country $country)
    {
        return $this->getPromo() == 1 ? $this->getTotalTaxedPromoPrice($country) : $this->getTotalTaxedPrice($country);
    }

    /**
     * @since Version 2.3
     * @param Country $country
     * @return float
     */
    public function getTotalTaxedPrice(Country $country)
    {
        $taxCalculator = new Calculator();

        return $taxCalculator->load($this->getProduct(), $country)->getTaxedPrice($this->getPrice()*$this->getQuantity());
    }

    /**
     * @since Version 2.3
     * @param Country $country
     * @return float
     */
    public function getTotalTaxedPromoPrice(Country $country)
    {
        $taxCalculator = new Calculator();

        return $taxCalculator->load($this->getProduct(), $country)->getTaxedPrice($this->getPromoPrice()*$this->getQuantity());
    }
}
