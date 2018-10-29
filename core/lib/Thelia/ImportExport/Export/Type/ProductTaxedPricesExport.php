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
use Thelia\ImportExport\Export\AbstractExport;
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
 * @author Thomas Arnaud <tarnaud@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ProductTaxedPricesExport extends AbstractExport
{
    const FILE_NAME = 'product_taxed_price';

    protected $orderAndAliases = [
        ProductSaleElementsTableMap::COL_ID => 'id',
        'productID' => 'product_id',
        'product_i18nTITLE' => 'title',
        'attribute_av_i18n_ATTRIBUTES' => 'attributes',
        ProductSaleElementsTableMap::COL_EAN_CODE => 'ean',
        'product_pricePRICE' => 'price',
        'product_pricePROMO_PRICE' => 'promo_price',
        'currencyCODE' => 'currency',
        ProductSaleElementsTableMap::COL_PROMO => 'promo',
        'tax_ruleID' => 'tax_id',
        'tax_rule_i18nTITLE' => 'tax_title'
    ];

    public function getData()
    {
        $locale = $this->language->getLocale();

        $productJoin = new Join(ProductTableMap::COL_ID, ProductI18nTableMap::COL_ID, Criteria::LEFT_JOIN);
        $attributeAvJoin = new Join(AttributeAvTableMap::COL_ID, AttributeAvI18nTableMap::COL_ID, Criteria::LEFT_JOIN);
        $taxRuleI18nJoin = new Join(TaxRuleTableMap::COL_ID, TaxRuleI18nTableMap::COL_ID, Criteria::LEFT_JOIN);

        $query = ProductSaleElementsQuery::create()
            ->addSelfSelectColumns()
            ->useProductPriceQuery()
                ->useCurrencyQuery()
                    ->withColumn(CurrencyTableMap::COL_CODE)
                    ->endUse()
                ->withColumn(ProductPriceTableMap::COL_PRICE)
                ->withColumn(ProductPriceTableMap::COL_PROMO_PRICE)
                ->endUse()
            ->useProductQuery()
                ->useTaxRuleQuery()
                    ->addJoinObject($taxRuleI18nJoin, 'tax_rule_i18n_join')
                    ->addJoinCondition(
                        'tax_rule_i18n_join',
                        TaxRuleI18nTableMap::COL_LOCALE . ' = ?',
                        $locale,
                        null,
                        \PDO::PARAM_STR
                    )
                    ->withColumn(TaxRuleTableMap::COL_ID)
                    ->withColumn(TaxRuleI18nTableMap::COL_TITLE)
                    ->endUse()
                ->addJoinObject($productJoin, 'product_join')
                ->addJoinCondition(
                    'product_join',
                    ProductI18nTableMap::COL_LOCALE . ' = ?',
                    $locale,
                    null,
                    \PDO::PARAM_STR
                )
                ->withColumn(ProductI18nTableMap::COL_TITLE)
                ->withColumn(ProductTableMap::COL_ID)
                ->endUse()
            ->useAttributeCombinationQuery(null, Criteria::LEFT_JOIN)
                ->useAttributeAvQuery(null, Criteria::LEFT_JOIN)
                    ->addJoinObject($attributeAvJoin, 'attribute_av_join')
                    ->addJoinCondition(
                        'attribute_av_join',
                        AttributeAvI18nTableMap::COL_LOCALE . ' = ?',
                        $locale,
                        null,
                        \PDO::PARAM_STR
                    )
                    ->addAsColumn(
                        'attribute_av_i18n_ATTRIBUTES',
                        'GROUP_CONCAT(DISTINCT ' . AttributeAvI18nTableMap::COL_TITLE . ')'
                    )
                    ->endUse()
                ->endUse()
            ->orderBy(ProductSaleElementsTableMap::COL_ID)
            ->groupBy(ProductSaleElementsTableMap::COL_ID)
        ;

//        $query = ProductSaleElementsQuery::create()
//            ->useProductPriceQuery()
//                ->useCurrencyQuery()
//                    ->addAsColumn("currency_CODE", CurrencyTableMap::COL_CODE)
//                ->endUse()
//                ->addAsColumn("price_PRICE", ProductPriceTableMap::COL_PRICE)
//                ->addAsColumn("price_PROMO_PRICE", ProductPriceTableMap::COL_PROMO_PRICE)
//            ->endUse()
//            ->useProductQuery()
//                ->useTaxRuleQuery()
//                    ->addJoinObject($taxRuleI18nJoin, "tax_rule_i18n_join")
//                    ->addJoinCondition(
//                        "tax_rule_i18n_join",
//                        TaxRuleI18nTableMap::COL_LOCALE . " = ?",
//                        $locale,
//                        null,
//                        \PDO::PARAM_STR
//                    )
//                    ->addAsColumn("tax_TITLE", TaxRuleI18nTableMap::COL_TITLE)
//                    ->addAsColumn("tax_ID", TaxRuleTableMap::COL_ID)
//                ->endUse()
//                ->addJoinObject($productI18nJoin, "product_i18n_join")
//                ->addJoinCondition(
//                    "product_i18n_join",
//                    ProductI18nTableMap::COL_LOCALE . " = ?",
//                    $locale,
//                    null,
//                    \PDO::PARAM_STR
//                )
//                ->addAsColumn("product_TITLE", ProductI18nTableMap::COL_TITLE)
//                ->addAsColumn("product_ID", ProductTableMap::COL_ID)
//            ->endUse()
//            ->useAttributeCombinationQuery(null, Criteria::LEFT_JOIN)
//                ->useAttributeAvQuery(null, Criteria::LEFT_JOIN)
//                    ->addJoinObject($attributeAvI18nJoin, "attribute_av_i18n_join")
//                    ->addJoinCondition(
//                        "attribute_av_i18n_join",
//                        AttributeAvI18nTableMap::COL_LOCALE . " = ?",
//                        $locale,
//                        null,
//                        \PDO::PARAM_STR
//                    )
//                    ->addAsColumn(
//                        "attribute_av_i18n_ATTRIBUTES",
//                        "GROUP_CONCAT(DISTINCT ".AttributeAvI18nTableMap::COL_TITLE.")"
//                    )
//                ->endUse()
//            ->endUse()
//            ->addAsColumn("product_sale_elements_ID", ProductSaleElementsTableMap::COL_ID)
//            ->addAsColumn("product_sale_elements_EAN_CODE", ProductSaleElementsTableMap::COL_EAN_CODE)
//            ->addAsColumn("product_sale_elements_PROMO", ProductSaleElementsTableMap::COL_PROMO)
//            ->select([
//                "product_sale_elements_ID",
//                "product_sale_elements_EAN_CODE",
//                "product_sale_elements_PROMO",
//                "price_PRICE",
//                "price_PROMO_PRICE",
//                "currency_CODE",
//                "product_TITLE",
//                "attribute_av_i18n_ATTRIBUTES",
//                "tax_TITLE",
//                "tax_ID"
//            ])
//            ->orderBy("product_sale_elements_ID")
//            ->groupBy("product_sale_elements_ID")
//        ;

        return $query;
    }
}
