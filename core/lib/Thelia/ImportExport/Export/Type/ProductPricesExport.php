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
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\FileFormat\FormatType;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\Model\Base\ProductSaleElementsQuery;
use Thelia\Model\Map\CurrencyTableMap;
use Thelia\Model\Map\ProductPriceTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;

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
     * @return \Thelia\Core\FileFormat\Formatting\FormatterData
     *
     * The method builds the FormatterData for the formatter
     */
    public function buildFormatterData()
    {
        $aliases = [
            ProductSaleElementsTableMap::REF => "ref",
            "price_PRICE" => "price",
            "price_PROMO_PRICE" => "promo_price",
            "currency_CODE" => "currency",
        ];

        $query = ProductSaleElementsQuery::create()
            ->useProductPriceQuery()
                ->useCurrencyQuery()
                    ->addAsColumn("currency_CODE", CurrencyTableMap::CODE)
                ->endUse()
                ->addAsColumn("price_PRICE", ProductPriceTableMap::PRICE)
                ->addAsColumn("price_PROMO_PRICE", ProductPriceTableMap::PROMO_PRICE)
            ->endUse()
            ->select([
                ProductSaleElementsTableMap::REF,
                "price_PRICE",
                "price_PROMO_PRICE",
                "currency_CODE",
            ])
        ;

        $data = new FormatterData($aliases);

        return $data->loadModelCriteria($query);
    }

} 