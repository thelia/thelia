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
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\FileFormat\FormatType;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\Model\Base\AttributeCombinationQuery;
use Thelia\Model\Lang;
use Thelia\Model\Map\AttributeAvI18nTableMap;
use Thelia\Model\Map\AttributeAvTableMap;
use Thelia\Model\Map\CurrencyTableMap;
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductPriceTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;

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
     * @param Lang $lang
     * @return \Thelia\Core\FileFormat\Formatting\FormatterData
     *
     * The method builds the FormatterData for the formatter
     */
    public function buildFormatterData(Lang $lang)
    {
        $aliases = [
            "product_sale_elements_REF" => "ref",
            "product_sale_elements_EAN_CODE" => "ean",
            "price_PRICE" => "price",
            "price_PROMO_PRICE" => "promo_price",
            "currency_CODE" => "currency",
            "product_TITLE" => "title",
            "attribute_av_i18n_ATTRIBUTES" => "attributes",
            "product_sale_elements_PROMO" => "promo",
        ];

        $locale = $this->real_escape($lang->getLocale());
        $defaultLocale = $this->real_escape(Lang::getDefaultLanguage()->getLocale());

        $query = AttributeCombinationQuery::create()
            ->useProductSaleElementsQuery()
                ->useProductPriceQuery()
                    ->useCurrencyQuery()
                        ->addAsColumn("currency_CODE", CurrencyTableMap::CODE)
                    ->endUse()
                    ->addAsColumn("price_PRICE", ProductPriceTableMap::PRICE)
                    ->addAsColumn("price_PROMO_PRICE", ProductPriceTableMap::PROMO_PRICE)
                ->endUse()
                ->useProductQuery()
                    ->useProductI18nQuery()
                        ->addAsColumn("product_TITLE", ProductI18nTableMap::TITLE)
                    ->endUse()
                ->endUse()
                ->addAsColumn("product_sale_elements_REF", ProductSaleElementsTableMap::REF)
                ->addAsColumn("product_sale_elements_EAN_CODE", ProductSaleElementsTableMap::EAN_CODE)
                ->addAsColumn("product_sale_elements_PROMO", ProductSaleElementsTableMap::PROMO)
            ->endUse()
            ->useAttributeAvQuery()
                ->useAttributeAvI18nQuery(null, Criteria::INNER_JOIN)
                    ->addAsColumn(
                        "attribute_av_i18n_ATTRIBUTES",
                        "GROUP_CONCAT(DISTINCT ".AttributeAvI18nTableMap::TITLE.")"
                    )
                ->endUse()
            ->endUse()
            ->select([
                "product_sale_elements_REF",
                "product_sale_elements_EAN_CODE",
                "product_sale_elements_PROMO",
                "price_PRICE",
                "price_PROMO_PRICE",
                "currency_CODE",
                "product_TITLE",
                "attribute_av_i18n_ATTRIBUTES",
            ])
            ->where(
                "CASE WHEN ".ProductTableMap::ID." IN".
                    "(SELECT DISTINCT ".ProductI18nTableMap::ID." ".
                    "FROM `".ProductI18nTableMap::TABLE_NAME."` ".
                    "WHERE locale=$locale) ".

                "THEN ".ProductI18nTableMap::LOCALE." = $locale ".
                "ELSE ".ProductI18nTableMap::LOCALE." = $defaultLocale ".
                "END"
            )
            ->_and()
            ->where(
                "CASE WHEN ".AttributeAvTableMap::ID." IN".
                    "(SELECT DISTINCT ".AttributeAvI18nTableMap::ID." ".
                    "FROM `".AttributeAvI18nTableMap::TABLE_NAME."` ".
                    "WHERE locale=$locale)".
                "THEN ".AttributeAvI18nTableMap::LOCALE." = $locale ".
                "ELSE ".AttributeAvI18nTableMap::LOCALE." = $defaultLocale ".
                "END"
            )
            ->groupBy("product_sale_elements_REF")
        ;

        $data = new FormatterData($aliases);

        return $data->loadModelCriteria($query);
    }

    protected function real_escape($str)
    {
        $return = "CONCAT(";
        $len = strlen($str);
        for($i = 0; $i < $len; ++$i) {
            $return .= "CHAR(".ord($str[$i])."),";
        }
        if ($i > 0) {
            $return = substr($return, 0, -1);
        }
        $return .= ")";

        return $return;
    }

} 