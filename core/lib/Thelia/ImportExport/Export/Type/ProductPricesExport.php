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
use Thelia\Core\FileFormat\FormatType;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\Model\Lang;
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
 */
class ProductPricesExport extends ExportHandler
{
    /**
     * @return string|array
     *
     * Define all the type of formatters that this can handle
     * return a string if it handle a single type ( specific exports ),
     * or an array if multiple.
     *
     * Thelia types are defined in \Thelia\Core\FileFormat\FormatType
     *
     * example:
     * return array(
     *     FormatType::TABLE,
     *     FormatType::UNBOUNDED,
     * );
     */
    public function getHandledTypes()
    {
        return array(
            FormatType::TABLE,
            FormatType::UNBOUNDED,
        );
    }

    /**
     * @param  Lang                                                                                   $lang
     * @return array|\Propel\Runtime\ActiveQuery\ModelCriteria|\Thelia\Core\Template\Element\BaseLoop
     */
    public function buildDataSet(Lang $lang)
    {
        $locale = $lang->getLocale();

        $productJoin = new Join(ProductTableMap::ID, ProductI18nTableMap::ID, Criteria::LEFT_JOIN);

        $attributeAvJoin = new Join(AttributeAvTableMap::ID, AttributeAvI18nTableMap::ID, Criteria::LEFT_JOIN);

        $query = ProductSaleElementsQuery::create()
            ->useProductPriceQuery()
                ->useCurrencyQuery()
                    ->addAsColumn("currency_CODE", CurrencyTableMap::CODE)
                ->endUse()
                ->addAsColumn("price_PRICE", ProductPriceTableMap::PRICE)
                ->addAsColumn("price_PROMO_PRICE", ProductPriceTableMap::PROMO_PRICE)
            ->endUse()
            ->useProductQuery()
                ->addJoinObject($productJoin, "product_join")
                ->addJoinCondition(
                    "product_join",
                    ProductI18nTableMap::LOCALE . " = ?",
                    $locale,
                    null,
                    \PDO::PARAM_STR
                )
                ->addAsColumn("product_TITLE", ProductI18nTableMap::TITLE)
                ->addAsColumn("product_ID", ProductTableMap::ID)
            ->endUse()
            ->useAttributeCombinationQuery()
                ->useAttributeAvQuery()
                    ->addJoinObject($attributeAvJoin, "attribute_av_join")
                    ->addJoinCondition(
                        "attribute_av_join",
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
                "attribute_av_i18n_ATTRIBUTES"
            ])
            ->orderBy("product_sale_elements_ID")
            ->groupBy("product_sale_elements_ID")
        ;

        return $query;
    }

    public function getOrder()
    {
        return [
            "id",
            "product_id",
            "title",
            "ean",
            "price",
            "promo_price",
            "currency",
            "promo",
            "attributes"
        ];
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
            "attribute_av_i18n_ATTRIBUTES" => "attributes"
        ];
    }
}
