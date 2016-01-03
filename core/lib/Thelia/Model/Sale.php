<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Sale\SaleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Sale as BaseSale;
use Thelia\Model\Tools\ModelEventDispatcherTrait;

class Sale extends BaseSale
{
    use ModelEventDispatcherTrait;

    /**
     * The price offsets types, either amount or percentage
     */
    const OFFSET_TYPE_PERCENTAGE = 10;
    const OFFSET_TYPE_AMOUNT = 20;

    /**
     * @return bool true if the sale has an end date, false otherwise
     */
    public function hasStartDate()
    {
        return ! is_null($this->getStartDate());
    }

    /**
     * @return bool true if the sale has a begin date, false otherwise
     */
    public function hasEndDate()
    {
        return ! is_null($this->getEndDate());
    }

    /**
     * Get the price offsets for each of the currencies.
     *
     * @return array an array of (currency ID => offset value)
     */
    public function getPriceOffsets()
    {
        $currencyOffsets = SaleOffsetCurrencyQuery::create()->filterBySaleId($this->getId())->find();

        $offsetList = [];

        /** @var SaleOffsetCurrency $currencyOffset */
        foreach ($currencyOffsets as $currencyOffset) {
            $offsetList[$currencyOffset->getCurrencyId()] = $currencyOffset->getPriceOffsetValue();
        }

        return $offsetList;
    }

    /**
     * Return the products included in this sale.
     *
     * @return array an array of Products
     */
    public function getSaleProductList()
    {
        $saleProducts = SaleProductQuery::create()->filterBySaleId($this->getId())->groupByProductId()->find();

        return $saleProducts;
    }

    /**
     * Return the selected attributes values for each of the selected products.
     *
     * @return array an array of (product ID => array of attribute availability ID)
     */
    public function getSaleProductsAttributeList()
    {
        $saleProducts = SaleProductQuery::create()->filterBySaleId($this->getId())->orderByProductId()->find();

        $selectedAttributes = [];

        $currentProduct = false;

        /** @var SaleProduct $saleProduct */
        foreach ($saleProducts as $saleProduct) {
            if ($currentProduct != $saleProduct->getProductId()) {
                $currentProduct = $saleProduct->getProductId();

                $selectedAttributes[$currentProduct] = [];
            }

            $selectedAttributes[$currentProduct][] = $saleProduct->getAttributeAvId();
        }

        return $selectedAttributes;
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_CREATESALE, new SaleEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATESALE, new SaleEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATESALE, new SaleEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATESALE, new SaleEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETESALE, new SaleEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_DELETESALE, new SaleEvent($this));
    }
}
