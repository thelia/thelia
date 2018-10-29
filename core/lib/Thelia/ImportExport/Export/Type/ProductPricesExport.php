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
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class ProductPricesExport
 * @package Thelia\ImportExport\Export\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 * @contributor Thomas Arnaud <tarnaud@openstudio.fr>
 */
class ProductPricesExport extends AbstractExport
{
    const FILE_NAME = 'product_price';

    protected $orderAndAliases = [
        ProductSaleElementsTableMap::COL_ID => 'id',
        'productID' => 'product_id',
        'product_i18nTITLE' => 'title',
        'attribute_av_i18n_ATTRIBUTES' => 'attributes',
        ProductSaleElementsTableMap::COL_EAN_CODE => 'ean',
        'product_pricePRICE' => 'price',
        'product_pricePROMO_PRICE' => 'promo_price',
        'currencyCODE' => 'currency',
        ProductSaleElementsTableMap::COL_PROMO => 'promo'
    ];

    protected function getData()
    {
        $locale = $this->language->getLocale();

        $productJoin = new Join(ProductTableMap::COL_ID, ProductI18nTableMap::COL_ID, Criteria::LEFT_JOIN);
        $attributeAvJoin = new Join(AttributeAvTableMap::COL_ID, AttributeAvI18nTableMap::COL_ID, Criteria::LEFT_JOIN);

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

        return $query;
    }
}
