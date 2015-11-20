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
use Thelia\Model\Map\ContentI18nTableMap;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\Content;
use Thelia\Model\Lang;
use Thelia\Model\ContentQuery;

/**
 * Class ContentI18Export
 * @package Thelia\ImportExport\Export\Type
 */
class ContentI18Export extends ExportHandler
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

        $query = ContentQuery::create();

        $urlJoin = new Join(ContentTableMap::ID, RewritingUrlTableMap::VIEW_ID, Criteria::LEFT_JOIN);
        $contentJoin = new Join(ContentTableMap::ID, ContentI18nTableMap::ID, Criteria::LEFT_JOIN);

        $query
            ->addJoinObject($urlJoin, "rewriting_url_join")
            ->addJoinCondition("rewriting_url_join", RewritingUrlTableMap::VIEW_LOCALE . " = ?", $locale, null,
                \PDO::PARAM_STR)
            ->addJoinCondition(
                "rewriting_url_join",
                RewritingUrlTableMap::VIEW . " = ?",
                (new Content())->getRewrittenUrlViewName(),
                null,
                \PDO::PARAM_STR
            )
            ->addJoinCondition("rewriting_url_join", "ISNULL(" . RewritingUrlTableMap::REDIRECTED . ")")
            ->addJoinObject($contentJoin, "content_join")
            ->addJoinCondition("content_join", ContentI18nTableMap::LOCALE . " = ?", $locale, null, \PDO::PARAM_STR);

        $query
            ->addAsColumn("content_ID", ContentTableMap::ID)
            ->addAsColumn("content_i18n_TITLE", ContentI18nTableMap::TITLE)
            ->addAsColumn("content_i18n_DESCRIPTION", ContentI18nTableMap::DESCRIPTION)
            ->addAsColumn("content_i18n_CHAPO", ContentI18nTableMap::CHAPO)
            ->addAsColumn("content_i18n_POSTSCRIPTUM", ContentI18nTableMap::POSTSCRIPTUM)
            ->addAsColumn("content_VISIBLE", ContentTableMap::VISIBLE)
            ->addAsColumn("content_seo_TITLE", ContentI18nTableMap::META_TITLE)
            ->addAsColumn("content_seo_META_DESCRIPTION", ContentI18nTableMap::META_DESCRIPTION)
            ->addAsColumn("content_seo_META_KEYWORDS", ContentI18nTableMap::META_KEYWORDS)
            ->addAsColumn("content_URL", RewritingUrlTableMap::URL)
            ->select([
                "content_ID",
                "content_VISIBLE",
                "content_i18n_TITLE",
                "content_i18n_DESCRIPTION",
                "content_i18n_CHAPO",
                "content_i18n_POSTSCRIPTUM",
                "content_URL",
                "content_seo_TITLE",
                "content_seo_META_DESCRIPTION",
                "content_seo_META_KEYWORDS",
            ]);

        return $query;
    }

    protected function getDefaultOrder()
    {
        return  [
            "id",
            "visible",
            "content_title",
            "content_description",
            "content_chapo",
            "content_postscriptum",
            "url",
            "page_title",
            "meta_description",
            "meta_keywords",
        ];
    }

    protected function getAliases()
    {
        return [
            "content_ID" => "id",
            "content_VISIBLE" => "visible",
            "content_i18n_TITLE" => "content_title",
            "content_i18n_DESCRIPTION" => "content_description",
            "content_i18n_CHAPO" => "content_chapo",
            "content_i18n_POSTSCRIPTUM" => "content_postscriptum",
            "content_URL" => "url",
            "content_seo_TITLE" => "page_title",
            "content_seo_META_DESCRIPTION" => "meta_description",
            "content_seo_META_KEYWORDS" => "meta_keywords",
        ];
    }
}
