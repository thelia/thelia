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
        ProductSaleElementsTableMap::ID => 'id',
        'productID' => 'product_id',
        'product_i18nTITLE' => 'title',
        'attribute_av_i18n_ATTRIBUTES' => 'attributes',
        ProductSaleElementsTableMap::EAN_CODE => 'ean',
        'product_pricePRICE' => 'price',
        'product_pricePROMO_PRICE' => 'promo_price',
        'currencyCODE' => 'currency',
        ProductSaleElementsTableMap::PROMO => 'promo',
        'tax_ruleID' => 'tax_id',
        'tax_rule_i18nTITLE' => 'tax_title'
    ];

    public function getData()
    {
        $locale = $this->language->getLocale();

        $productJoin = new Join(ProductTableMap::ID, ProductI18nTableMap::ID, Criteria::LEFT_JOIN);
        $attributeAvJoin = new Join(AttributeAvTableMap::ID, AttributeAvI18nTableMap::ID, Criteria::LEFT_JOIN);
        $taxRuleI18nJoin = new Join(TaxRuleTableMap::ID, TaxRuleI18nTableMap::ID, Criteria::LEFT_JOIN);

        $query = ProductSaleElementsQuery::create()
            ->addSelfSelectColumns()
            ->useProductPriceQuery()
                ->useCurrencyQuery()
                    ->withColumn(CurrencyTableMap::CODE)
                    ->endUse()
                ->withColumn(ProductPriceTableMap::PRICE)
                ->withColumn(ProductPriceTableMap::PROMO_PRICE)
                ->endUse()
            ->useProductQuery()
                ->useTaxRuleQuery()
                    ->addJoinObject($taxRuleI18nJoin, 'tax_rule_i18n_join')
                    ->addJoinCondition(
                        'tax_rule_i18n_join',
                        TaxRuleI18nTableMap::LOCALE . ' = ?',
                        $locale,
                        null,
                        \PDO::PARAM_STR
                    )
                    ->withColumn(TaxRuleTableMap::ID)
                    ->withColumn(TaxRuleI18nTableMap::TITLE)
                    ->endUse()
                ->addJoinObject($productJoin, 'product_join')
                ->addJoinCondition(
                    'product_join',
                    ProductI18nTableMap::LOCALE . ' = ?',
                    $locale,
                    null,
                    \PDO::PARAM_STR
                )
                ->withColumn(ProductI18nTableMap::TITLE)
                ->withColumn(ProductTableMap::ID)
                ->endUse()
            ->useAttributeCombinationQuery(null, Criteria::LEFT_JOIN)
                ->useAttributeAvQuery(null, Criteria::LEFT_JOIN)
                    ->addJoinObject($attributeAvJoin, 'attribute_av_join')
                    ->addJoinCondition(
                        'attribute_av_join',
                        AttributeAvI18nTableMap::LOCALE . ' = ?',
                        $locale,
                        null,
                        \PDO::PARAM_STR
                    )
                    ->addAsColumn(
                        'attribute_av_i18n_ATTRIBUTES',
                        'GROUP_CONCAT(DISTINCT ' . AttributeAvI18nTableMap::TITLE . ')'
                    )
                    ->endUse()
                ->endUse()
            ->orderBy(ProductSaleElementsTableMap::ID)
            ->groupBy(ProductSaleElementsTableMap::ID)
        ;

//        $query = ProductSaleElementsQuery::create()
//            ->useProductPriceQuery()
//                ->useCurrencyQuery()
//                    ->addAsColumn("currency_CODE", CurrencyTableMap::CODE)
//                ->endUse()
//                ->addAsColumn("price_PRICE", ProductPriceTableMap::PRICE)
//                ->addAsColumn("price_PROMO_PRICE", ProductPriceTableMap::PROMO_PRICE)
//            ->endUse()
//            ->useProductQuery()
//                ->useTaxRuleQuery()
//                    ->addJoinObject($taxRuleI18nJoin, "tax_rule_i18n_join")
//                    ->addJoinCondition(
//                        "tax_rule_i18n_join",
//                        TaxRuleI18nTableMap::LOCALE . " = ?",
//                        $locale,
//                        null,
//                        \PDO::PARAM_STR
//                    )
//                    ->addAsColumn("tax_TITLE", TaxRuleI18nTableMap::TITLE)
//                    ->addAsColumn("tax_ID", TaxRuleTableMap::ID)
//                ->endUse()
//                ->addJoinObject($productI18nJoin, "product_i18n_join")
//                ->addJoinCondition(
//                    "product_i18n_join",
//                    ProductI18nTableMap::LOCALE . " = ?",
//                    $locale,
//                    null,
//                    \PDO::PARAM_STR
//                )
//                ->addAsColumn("product_TITLE", ProductI18nTableMap::TITLE)
//                ->addAsColumn("product_ID", ProductTableMap::ID)
//            ->endUse()
//            ->useAttributeCombinationQuery(null, Criteria::LEFT_JOIN)
//                ->useAttributeAvQuery(null, Criteria::LEFT_JOIN)
//                    ->addJoinObject($attributeAvI18nJoin, "attribute_av_i18n_join")
//                    ->addJoinCondition(
//                        "attribute_av_i18n_join",
//                        AttributeAvI18nTableMap::LOCALE . " = ?",
//                        $locale,
//                        null,
//                        \PDO::PARAM_STR
//                    )
//                    ->addAsColumn(
//                        "attribute_av_i18n_ATTRIBUTES",
//                        "GROUP_CONCAT(DISTINCT ".AttributeAvI18nTableMap::TITLE.")"
//                    )
//                ->endUse()
//            ->endUse()
//            ->addAsColumn("product_sale_elements_ID", ProductSaleElementsTableMap::ID)
//            ->addAsColumn("product_sale_elements_EAN_CODE", ProductSaleElementsTableMap::EAN_CODE)
//            ->addAsColumn("product_sale_elements_PROMO", ProductSaleElementsTableMap::PROMO)
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
