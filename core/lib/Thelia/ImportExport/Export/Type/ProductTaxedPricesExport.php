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
use Thelia\Model\Lang;
use Thelia\Model\Map\AttributeAvI18nTableMap;
use Thelia\Model\Map\AttributeAvTableMap;
use Thelia\Model\Map\CurrencyTableMap;
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductPriceTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\TaxRuleI18nTableMap;
use Thelia\Model\Map\TaxRuleTableMap;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class ProductTaxedPricesExport
 * @package Thelia\ImportExport\Export\Type
 * @author Thomas Arnaud <tarnaud@openstudio.fr>
 */
class ProductTaxedPricesExport extends ProductPricesExport
{
    public function buildDataSet(Lang $lang)
    {
        $locale = $lang->getLocale();

        $productI18nJoin = new Join(ProductTableMap::ID, ProductI18nTableMap::ID, Criteria::LEFT_JOIN);

        $attributeAvI18nJoin = new Join(AttributeAvTableMap::ID, AttributeAvI18nTableMap::ID, Criteria::LEFT_JOIN);

        $taxRuleI18nJoin = new Join(TaxRuleTableMap::ID, TaxRuleI18nTableMap::ID, Criteria::LEFT_JOIN);

        $query = ProductSaleElementsQuery::create()
            ->useProductPriceQuery()
                ->useCurrencyQuery()
                    ->addAsColumn("currency_CODE", CurrencyTableMap::CODE)
                ->endUse()
                ->addAsColumn("price_PRICE", ProductPriceTableMap::PRICE)
                ->addAsColumn("price_PROMO_PRICE", ProductPriceTableMap::PROMO_PRICE)
            ->endUse()
            ->useProductQuery()
                ->useTaxRuleQuery()
                    ->addJoinObject($taxRuleI18nJoin, "tax_rule_i18n_join")
                    ->addJoinCondition(
                        "tax_rule_i18n_join",
                        TaxRuleI18nTableMap::LOCALE . " = ?",
                        $locale,
                        null,
                        \PDO::PARAM_STR
                    )
                    ->addAsColumn("tax_TITLE", TaxRuleI18nTableMap::TITLE)
                    ->addAsColumn("tax_ID", TaxRuleTableMap::ID)
                ->endUse()
                ->addJoinObject($productI18nJoin, "product_i18n_join")
                ->addJoinCondition(
                    "product_i18n_join",
                    ProductI18nTableMap::LOCALE . " = ?",
                    $locale,
                    null,
                    \PDO::PARAM_STR
                )
                ->addAsColumn("product_TITLE", ProductI18nTableMap::TITLE)
                ->addAsColumn("product_ID", ProductTableMap::ID)
            ->endUse()
            ->useAttributeCombinationQuery(null, Criteria::LEFT_JOIN)
                ->useAttributeAvQuery(null, Criteria::LEFT_JOIN)
                    ->addJoinObject($attributeAvI18nJoin, "attribute_av_i18n_join")
                    ->addJoinCondition(
                        "attribute_av_i18n_join",
                        AttributeAvI18nTableMap::LOCALE . " = ?",
                        $locale,
                        null,
                        \PDO::PARAM_STR
                    )
                    ->addAsColumn(
                        "attribute_av_i18n_ATTRIBUTES",
                        "GROUP_CONCAT(DISTINCT ".AttributeAvI18nTableMap::TITLE.")"
                    )
                ->endUse()
            ->endUse()
            ->addAsColumn("product_sale_elements_ID", ProductSaleElementsTableMap::ID)
            ->addAsColumn("product_sale_elements_EAN_CODE", ProductSaleElementsTableMap::EAN_CODE)
            ->addAsColumn("product_sale_elements_PROMO", ProductSaleElementsTableMap::PROMO)
            ->select([
                "product_sale_elements_ID",
                "product_sale_elements_EAN_CODE",
                "product_sale_elements_PROMO",
                "price_PRICE",
                "price_PROMO_PRICE",
                "currency_CODE",
                "product_TITLE",
                "attribute_av_i18n_ATTRIBUTES",
                "tax_TITLE",
                "tax_ID"
            ])
            ->orderBy("product_sale_elements_ID")
            ->groupBy("product_sale_elements_ID")
        ;

        return $query;
    }

    protected function getAliases()
    {
        return [
            "product_sale_elements_ID" => "id",
            "product_sale_elements_EAN_CODE" => "ean",
            "price_PRICE" => "price",
            "price_PROMO_PRICE" => "promo_price",
            "currency_CODE" => "currency",
            "product_TITLE" => "title",
            "product_sale_elements_PROMO" => "promo",
            "attribute_av_i18n_ATTRIBUTES" => "attributes",
            "tax_TITLE" => "tax_title",
            "tax_id" => "tax_id",
        ];
    }

    public function getOrder()
    {
        return [
            "id",
            "product_id",
            "title",
            "attributes",
            "ean",
            "price",
            "promo_price",
            "currency",
            "promo",
            "tax_id",
            "tax_title",
        ];
    }
}
