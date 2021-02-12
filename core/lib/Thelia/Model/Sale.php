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
use Thelia\Core\Event\Sale\SaleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Sale as BaseSale;

class Sale extends BaseSale
{
    /**
     * The price offsets types, either amount or percentage
     */
    public const OFFSET_TYPE_PERCENTAGE = 10;
    public const OFFSET_TYPE_AMOUNT = 20;

    /**
     * @return bool true if the sale has an end date, false otherwise
     */
    public function hasStartDate()
    {
        return ! \is_null($this->getStartDate());
    }

    /**
     * @return bool true if the sale has a begin date, false otherwise
     */
    public function hasEndDate()
    {
        return ! \is_null($this->getEndDate());
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
}
