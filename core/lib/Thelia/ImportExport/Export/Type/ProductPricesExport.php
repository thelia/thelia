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
use Propel\Runtime\Propel;
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\FileFormat\FormatType;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\Lang;
use Thelia\Model\Map\CurrencyTableMap;
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductPriceTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\Tools\ModelCriteriaTools;

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
            ProductSaleElementsTableMap::REF => "ref",
            ProductSaleElementsTableMap::EAN_CODE => "ean",
            "price_PRICE" => "price",
            "price_PROMO_PRICE" => "promo_price",
            "currency_CODE" => "currency",
            "product_TITLE" => "title",
        ];

        $locale = $this->real_escape($lang->getLocale());
        $defaultLocale = $this->real_escape(Lang::getDefaultLanguage()->getLocale());

        $query = ProductSaleElementsQuery::create()
            ->useProductPriceQuery()
                ->useCurrencyQuery()
                    ->addAsColumn("currency_CODE", CurrencyTableMap::CODE)
                ->endUse()
                ->addAsColumn("price_PRICE", ProductPriceTableMap::PRICE)
                ->addAsColumn("price_PROMO_PRICE", ProductPriceTableMap::PROMO_PRICE)
            ->endUse()
            ->useProductQuery()
                ->useProductI18nQuery(null, Criteria::LEFT_JOIN)
                    ->addAsColumn("product_TITLE", ProductI18nTableMap::TITLE)
                ->endUse()
            ->endUse()
            ->select([
                ProductSaleElementsTableMap::REF,
                ProductSaleElementsTableMap::EAN_CODE,
                "price_PRICE",
                "price_PROMO_PRICE",
                "currency_CODE",
                "product_TITLE"
            ])
            ->where(
                "CASE WHEN `product`.ID IN".
                "(SELECT DISTINCT id FROM product_i18n WHERE locale=$locale)".
                    "THEN product_i18n.locale = $locale ".
                    "ELSE product_i18n.locale = $defaultLocale ".
                "END"
            )
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