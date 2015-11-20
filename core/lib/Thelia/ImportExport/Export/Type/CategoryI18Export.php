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
use Thelia\Model\Map\CategoryI18nTableMap;
use Thelia\Model\Map\CategoryTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\Category;
use Thelia\Model\Lang;
use Thelia\Model\CategoryQuery;

/**
 * Class CategoryI18Export
 * @package Thelia\ImportExport\Export\Type
 */
class CategoryI18Export extends ExportHandler
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

        $query = CategoryQuery::create();

        $urlJoin = new Join(CategoryTableMap::ID, RewritingUrlTableMap::VIEW_ID, Criteria::LEFT_JOIN);
        $categoryJoin = new Join(CategoryTableMap::ID, CategoryI18nTableMap::ID, Criteria::LEFT_JOIN);

        $query
            ->addJoinObject($urlJoin, "rewriting_url_join")
            ->addJoinCondition("rewriting_url_join", RewritingUrlTableMap::VIEW_LOCALE . " = ?", $locale, null,
                \PDO::PARAM_STR)
            ->addJoinCondition(
                "rewriting_url_join",
                RewritingUrlTableMap::VIEW . " = ?",
                (new Category())->getRewrittenUrlViewName(),
                null,
                \PDO::PARAM_STR
            )
            ->addJoinCondition("rewriting_url_join", "ISNULL(" . RewritingUrlTableMap::REDIRECTED . ")")
            ->addJoinObject($categoryJoin, "category_join")
            ->addJoinCondition("category_join", CategoryI18nTableMap::LOCALE . " = ?", $locale, null, \PDO::PARAM_STR);

        $query
            ->addAsColumn("category_ID", CategoryTableMap::ID)
            ->addAsColumn("category_i18n_TITLE", CategoryI18nTableMap::TITLE)
            ->addAsColumn("category_i18n_DESCRIPTION", CategoryI18nTableMap::DESCRIPTION)
            ->addAsColumn("category_i18n_CHAPO", CategoryI18nTableMap::CHAPO)
            ->addAsColumn("category_i18n_POSTSCRIPTUM", CategoryI18nTableMap::POSTSCRIPTUM)
            ->addAsColumn("category_VISIBLE", CategoryTableMap::VISIBLE)
            ->addAsColumn("category_seo_TITLE", CategoryI18nTableMap::META_TITLE)
            ->addAsColumn("category_seo_META_DESCRIPTION", CategoryI18nTableMap::META_DESCRIPTION)
            ->addAsColumn("category_seo_META_KEYWORDS", CategoryI18nTableMap::META_KEYWORDS)
            ->addAsColumn("category_URL", RewritingUrlTableMap::URL)
            ->select([
                "category_ID",
                "category_VISIBLE",
                "category_i18n_TITLE",
                "category_i18n_DESCRIPTION",
                "category_i18n_CHAPO",
                "category_i18n_POSTSCRIPTUM",
                "category_URL",
                "category_seo_TITLE",
                "category_seo_META_DESCRIPTION",
                "category_seo_META_KEYWORDS",
            ]);

        return $query;
    }

    protected function getDefaultOrder()
    {
        return  [
            "id",
            "visible",
            "category_title",
            "category_description",
            "category_chapo",
            "category_postscriptum",
            "url",
            "page_title",
            "meta_description",
            "meta_keywords",
        ];
    }

    protected function getAliases()
    {
        return [
            "category_ID" => "id",
            "category_VISIBLE" => "visible",
            "category_i18n_TITLE" => "category_title",
            "category_i18n_DESCRIPTION" => "category_description",
            "category_i18n_CHAPO" => "category_chapo",
            "category_i18n_POSTSCRIPTUM" => "category_postscriptum",
            "category_URL" => "url",
            "category_seo_TITLE" => "page_title",
            "category_seo_META_DESCRIPTION" => "meta_description",
            "category_seo_META_KEYWORDS" => "meta_keywords",
        ];
    }
}
