<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\ImportExport\Export\Type;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Lang;
use Thelia\Model\Map\AttributeCombinationTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\TaxRuleI18nTableMap;
use Thelia\Model\Map\TaxRuleTableMap;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Tools\I18n;

/**
 * Class ProductTaxedPricesExport
 * @package Thelia\ImportExport\Export\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductTaxedPricesExport extends ProductPricesExport
{
    public function buildDataSet(Lang $lang)
    {
        /** @var \Thelia\Model\AttributeCombinationQuery $query */
        $query = parent::buildDataSet($lang);

        $pseJoin = new Join(AttributeCombinationTableMap::PRODUCT_SALE_ELEMENTS_ID, ProductSaleElementsTableMap::ID);
        $pseJoin->setRightTableAlias("pse_tax_join");

        $productJoin = new Join(ProductSaleElementsTableMap::ID, ProductTableMap::ID);
        $productJoin->setRightTableAlias("product_tax_join");

        $taxJoin = new Join("`product_tax_join`.TAX_RULE_ID", TaxRuleTableMap::ID, Criteria::LEFT_JOIN);
        $taxI18nJoin = new Join(TaxRuleTableMap::ID, TaxRuleI18nTableMap::ID, Criteria::LEFT_JOIN);

        $query
            ->addJoinObject($pseJoin, "pse_tax_join")
            ->addJoinObject($productJoin, "product_tax_join")
            ->addJoinObject($productJoin)
            ->addJoinObject($taxJoin)
            ->addJoinObject($taxI18nJoin)
            ->addAsColumn("product_TAX_TITLE", TaxRuleI18nTableMap::TITLE)
            ->addAsColumn("tax_ID", TaxRuleTableMap::ID)
            ->select($query->getSelect() + [
                "product_TAX_TITLE",
                "tax_ID",
            ])
        ;

        I18n::addI18nCondition(
            $query,
            TaxRuleI18nTableMap::TABLE_NAME,
            TaxRuleTableMap::ID,
            TaxRuleI18nTableMap::ID,
            TaxRuleI18nTableMap::LOCALE,
            $lang->getLocale()
        );

        $dataSet = $query
            ->keepQuery(true)
            ->find()
            ->toArray()
        ;

        $productSaleElements = ProductSaleElementsQuery::create()
            ->find()
            ->toKeyIndex("Id")
        ;

        $currencies = CurrencyQuery::create()
            ->find()
            ->toKeyIndex("Code")
        ;

        foreach ($dataSet as &$line) {
            /** @var \Thelia\Model\ProductSaleElements $pse */
            $pse = $productSaleElements[$line["product_sale_elements_ID"]];

            $pricesTools = $pse->getPricesByCurrency($currencies[$line["currency_CODE"]]);
            $line["price_PRICE"] = $pricesTools->getPrice();
            $line["price_PROMO_PRICE"] = $pricesTools->getPromoPrice();
        }

        return $dataSet;
    }
}
