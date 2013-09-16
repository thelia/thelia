<?php

namespace Thelia\Model;

use Propel\Runtime\Exception\PropelException;
use Thelia\Model\Base\Product as BaseProduct;
use Thelia\Tools\URL;
use Thelia\TaxEngine\Calculator;

class Product extends BaseProduct
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    use \Thelia\Model\Tools\PositionManagementTrait;

    use \Thelia\Model\Tools\UrlRewritingTrait;

    /**
     * {@inheritDoc}
     */
    protected function getRewritenUrlViewName() {
        return 'product';
    }

    public function getRealLowestPrice($virtualColumnName = 'real_lowest_price')
    {
        try {
            $amount = $this->getVirtualColumn($virtualColumnName);
        }
        catch(PropelException $e) {
            throw new PropelException("Virtual column `$virtualColumnName` does not exist in Product::getRealLowestPrice");
        }

        return $amount;
    }

    public function getTaxedPrice(Country $country)
    {
        $taxCalculator = new Calculator();
        return $taxCalculator->load($this, $country)->getTaxedPrice($this->getRealLowestPrice());
    }

    /**
     * Calculate next position relative to our default category
     */
    protected function addCriteriaToPositionQuery($query) {

        // TODO: Find the default category for this product,
        // and generate the position relative to this category

    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        $this->generateRewritenUrl($this->getLocale());

        return true;
    }
}
