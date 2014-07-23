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
use Thelia\Model\Map\ContentI18nTableMap;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductAssociatedContentQuery;
use Thelia\Model\Lang;

/**
 * Class ProductSEOExport
 * @package Thelia\ImportExport\Export\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductSEOExport extends ExportHandler
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
     * @param \Thelia\Model\Lang $lang
     * @return \Thelia\Core\FileFormat\Formatting\FormatterData
     *
     * The method builds the FormatterData for the formatter
     */
    public function buildFormatterData(Lang $lang)
    {
        $aliases = [
            "product_REF" => "ref",
            "product_VISIBLE" => "visible",
            "product_i18n_TITLE" => "product_title",
            "content_URL" => "url",
            "content_TITLE" => "page_title",
            "content_META_DESCRIPTION" => "meta_description",
            "content_META_KEYWORDS" => "meta_keywords",
        ];

        $locale = $lang->getLocale();

        $query = ProductAssociatedContentQuery::create()
            ->useContentQuery()
                ->useContentI18nQuery()
                    ->addAsColumn("content_URL", "")
                    ->addAsColumn("content_TITLE", ContentI18nTableMap::TITLE)
                    ->addAsColumn("content_META_DESCRIPTION", ContentI18nTableMap::META_DESCRIPTION)
                    ->addAsColumn("content_META_KEYWORDS", ContentI18nTableMap::META_KEYWORDS)
                ->endUse()
            ->endUse()
            ->useProductQuery()
                ->useProductI18nQuery()
                    ->addAsColumn("product_i18n_TITLE", ProductI18nTableMap::TITLE)
                ->endUse()
                ->addAsColumn("product_REF", ProductTableMap::REF)
                ->addAsColumn("product_VISIBLE", ProductTableMap::VISIBLE)
            ->endUse()
            ->select([
                "product_REF",
                "product_VISIBLE",
                "product_i18n_TITLE",
                "content_URL",
                "content_TITLE",
                "content_META_DESCRIPTION",
                "content_META_KEYWORDS",
            ])
        ;

        $this->addI18nCondition(
            $query,
            ContentI18nTableMap::TABLE_NAME,
            ContentTableMap::ID,
            ContentI18nTableMap::ID,
            ContentI18nTableMap::LOCALE,
            $locale
        );

        $this->addI18nCondition(
            $query,
            ProductI18nTableMap::TABLE_NAME,
            ProductTableMap::ID,
            ProductI18nTableMap::ID,
            ProductI18nTableMap::LOCALE,
            $locale
        );

        $data = new FormatterData($aliases);

        return $data->loadModelCriteria($query);
    }

} 