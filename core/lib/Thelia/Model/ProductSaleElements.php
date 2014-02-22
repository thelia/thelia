<?php

namespace Thelia\Model;

use Thelia\Model\Base\ProductSaleElements as BaseProductSaleElements;
use Thelia\TaxEngine\Calculator;

class ProductSaleElements extends BaseProductSaleElements
{
    public function getPrice($virtualColumnName = 'price_PRICE')
    {
        try {
            $amount = $this->getVirtualColumn($virtualColumnName);
        } catch (PropelException $e) {
            throw new PropelException("Virtual column `$virtualColumnName` does not exist in ProductSaleElements::getPrice");
        }

        return $amount;
    }

    public function getPromoPrice($virtualColumnName = 'price_PROMO_PRICE')
    {
        try {
            $amount = $this->getVirtualColumn($virtualColumnName);
        } catch (PropelException $e) {
            throw new PropelException("Virtual column `$virtualColumnName` does not exist in ProductSaleElements::getPromoPrice");
        }

        return $amount;
    }

    public function getTaxedPrice(Country $country)
    {
        $taxCalculator = new Calculator();

        return round($taxCalculator->load($this->getProduct(), $country)->getTaxedPrice($this->getPrice()), 2);
    }

    public function getTaxedPromoPrice(Country $country)
    {
        $taxCalculator = new Calculator();

        return round($taxCalculator->load($this->getProduct(), $country)->getTaxedPrice($this->getPromoPrice()), 2);
    }
}
