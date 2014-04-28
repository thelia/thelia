<?php

namespace Thelia\Model;

use Thelia\Model\Base\ProductPriceQuery as BaseProductPriceQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'product_price' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class ProductPriceQuery extends BaseProductPriceQuery
{

    public function findByCurrencyAndProductSaleElements($currencyId, $productSaleElementsId)
    {
        ArticleQuery::create()->select(array('Id', 'Name'))->find();

        $currencyId = $this->getCurrency();
        if (null !== $currencyId) {
            $currency = CurrencyQuery::create()->findOneById($currencyId);
            if (null === $currency) {
                throw new \InvalidArgumentException('Cannot found currency id: `' . $currency . '` in product_sale_elements loop');
            }
        }

    }

} // ProductPriceQuery
