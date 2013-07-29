<?php

namespace Thelia\Model;

use Thelia\Model\Base\Product as BaseProduct;
use Thelia\Model\Base\StockQuery;

class Product extends BaseProduct {

    public function stockIsValid($value, $combination = null)
    {
        $isValid = true;

        if (ConfigQuery::read("verifyStock", 1) == 1) {

            $stock = StockQuery::create()
                ->filterByProduct($this);

            if ($combination) {
                $stock->filterByCombinationId($combination);
            }

            $stock = $stock->findOne();

            if ($stock) {

                if($stock->getQuantity() < $value) {
                    $isValid = false;
                }

            } else {
                $isValid = false;
            }

        }

        return $isValid;
    }

}
