<?php

namespace Thelia\Model;

use Propel\Runtime\Exception\PropelException;
use Thelia\Model\Base\Product as BaseProduct;
use Thelia\Tools\URL;
use Thelia\TaxEngine\Calculator;

class Product extends BaseProduct
{
    public function getUrl($locale)
    {
        return URL::getInstance()->retrieve('product', $this->getId(), $locale)->toString();
    }

    public function getRealLowestPrice($virtualColumnName = 'real_lowest_price')
    {
        try {
            $amount = $this->getVirtualColumn($virtualColumnName);
        } catch(PropelException $e) {
            throw new PropelException("Virtual column `$virtualColumnName` does not exist in Product::getRealLowestPrice");
        }

        return $amount;
    }

    public function getTaxedPrice(Country $country)
    {
        $taxCalculator = new Calculator();
        return $taxCalculator->load($this, $country)->getTaxedPrice($this->getRealLowestPrice());
    }
}
