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
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\FileFormat\FormatType;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\Model\Map\ContentI18nTableMap;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\Product;
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
            "product_URL" => "url",
            "content_TITLE" => "content_title",
            "content_META_DESCRIPTION" => "meta_description",
            "content_META_KEYWORDS" => "meta_keywords",
        ];

        $locale = $this->locale = $lang->getLocale();

        /**
         * Join objects
         */
        $urlJoin = new Join(ProductTableMap::ID, RewritingUrlTableMap::VIEW_ID, Criteria::LEFT_JOIN);
        $contentJoin = new Join(ContentTableMap::ID, ContentI18nTableMap::ID, Criteria::LEFT_JOIN);
        $productJoin = new Join(ProductTableMap::ID, ProductI18nTableMap::ID, Criteria::LEFT_JOIN);

        $query = ProductAssociatedContentQuery::create()
            ->useContentQuery()
                ->addJoinObject($contentJoin, "content_join")
                ->addJoinCondition("content_join", ContentI18nTableMap::LOCALE . "=" . $this->real_escape($locale))
                ->addAsColumn("content_TITLE", ContentI18nTableMap::TITLE)
                ->addAsColumn("content_META_DESCRIPTION", ContentI18nTableMap::META_DESCRIPTION)
                ->addAsColumn("content_META_KEYWORDS", ContentI18nTableMap::META_KEYWORDS)
            ->endUse()
            ->useProductQuery()
                ->addJoinObject($productJoin, "product_join")
                ->addJoinCondition("product_join", ProductI18nTableMap::LOCALE . "=" . $this->real_escape($locale))
                ->addAsColumn("product_i18n_TITLE", ProductI18nTableMap::TITLE)
                ->addAsColumn("product_REF", ProductTableMap::REF)
                ->addAsColumn("product_VISIBLE", ProductTableMap::VISIBLE)
            ->endUse()
            ->addJoinObject($urlJoin, "rewriting_url_join")
            ->addJoinCondition("rewriting_url_join", RewritingUrlTableMap::VIEW_LOCALE . "=" . $this->real_escape($locale))
            ->addJoinCondition(
                "rewriting_url_join",
                RewritingUrlTableMap::VIEW . "=" . $this->real_escape((new Product())->getRewrittenUrlViewName())
            )
            ->addJoinCondition("rewriting_url_join", "ISNULL(".RewritingUrlTableMap::REDIRECTED.")")
            ->addAsColumn("product_URL", RewritingUrlTableMap::URL)
            ->select([
                "product_REF",
                "product_VISIBLE",
                "product_i18n_TITLE",
                "product_URL",
                "content_TITLE",
                "content_META_DESCRIPTION",
                "content_META_KEYWORDS",
            ])
        ;

        $data = new FormatterData($aliases);

        return $data->loadModelCriteria($query);
    }

    protected function getDefaultOrder()
    {
        return  [
            "ref",
            "product_title",
            "visible",
            "url",
            "content_title",
            "meta_description",
            "meta_keywords",
        ];
    }



} 