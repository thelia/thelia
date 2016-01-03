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
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\Product;
use Thelia\Model\Lang;
use Thelia\Model\ProductQuery;

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
     * @param  Lang                                            $lang
     * @return array|\Propel\Runtime\ActiveQuery\ModelCriteria
     */
    public function buildDataSet(Lang $lang)
    {
        $locale = $this->locale = $lang->getLocale();

        $query = ProductQuery::create();
        $urlJoin = new Join(ProductTableMap::ID, RewritingUrlTableMap::VIEW_ID, Criteria::LEFT_JOIN);
        $productJoin = new Join(ProductTableMap::ID, ProductI18nTableMap::ID, Criteria::LEFT_JOIN);

        $query
            ->addJoinObject($urlJoin, "rewriting_url_join")
            ->addJoinCondition("rewriting_url_join", RewritingUrlTableMap::VIEW_LOCALE . " = ?", $locale, null,
                \PDO::PARAM_STR)
            ->addJoinCondition(
                "rewriting_url_join",
                RewritingUrlTableMap::VIEW . " = ?",
                (new Product())->getRewrittenUrlViewName(),
                null,
                \PDO::PARAM_STR
            )
            ->addJoinCondition("rewriting_url_join", "ISNULL(" . RewritingUrlTableMap::REDIRECTED . ")")
            ->addJoinObject($productJoin, "product_join")
            ->addJoinCondition("product_join", ProductI18nTableMap::LOCALE . " = ?", $locale, null, \PDO::PARAM_STR);

        $query
            ->addAsColumn("product_i18n_TITLE", ProductI18nTableMap::TITLE)
            ->addAsColumn("product_REF", ProductTableMap::REF)
            ->addAsColumn("product_VISIBLE", ProductTableMap::VISIBLE)
            ->addAsColumn("product_seo_TITLE", ProductI18nTableMap::META_TITLE)
            ->addAsColumn("product_seo_META_DESCRIPTION", ProductI18nTableMap::META_DESCRIPTION)
            ->addAsColumn("product_seo_META_KEYWORDS", ProductI18nTableMap::META_KEYWORDS)
            ->addAsColumn("product_URL", RewritingUrlTableMap::URL)
            ->select([
                "product_REF",
                "product_VISIBLE",
                "product_i18n_TITLE",
                "product_URL",
                "product_seo_TITLE",
                "product_seo_META_DESCRIPTION",
                "product_seo_META_KEYWORDS",
            ]);

        return $query;
    }

    protected function getDefaultOrder()
    {
        return  [
            "ref",
            "product_title",
            "visible",
            "url",
            "page_title",
            "meta_description",
            "meta_keywords",
        ];
    }

    protected function getAliases()
    {
        return [
            "product_REF" => "ref",
            "product_VISIBLE" => "visible",
            "product_i18n_TITLE" => "product_title",
            "product_URL" => "url",
            "product_seo_TITLE" => "page_title",
            "product_seo_META_DESCRIPTION" => "meta_description",
            "product_seo_META_KEYWORDS" => "meta_keywords",
        ];
    }
}
