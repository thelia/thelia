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
use Thelia\Model\Map\FolderI18nTableMap;
use Thelia\Model\Map\FolderTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\Folder;
use Thelia\Model\Lang;
use Thelia\Model\FolderQuery;

/**
 * Class FolderI18Export
 * @package Thelia\ImportExport\Export\Type
 */
class FolderI18Export extends ExportHandler
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

        $query = FolderQuery::create();

        $urlJoin = new Join(FolderTableMap::ID, RewritingUrlTableMap::VIEW_ID, Criteria::LEFT_JOIN);
        $folderJoin = new Join(FolderTableMap::ID, FolderI18nTableMap::ID, Criteria::LEFT_JOIN);

        $query
            ->addJoinObject($urlJoin, "rewriting_url_join")
            ->addJoinCondition("rewriting_url_join", RewritingUrlTableMap::VIEW_LOCALE . " = ?", $locale, null,
                \PDO::PARAM_STR)
            ->addJoinCondition(
                "rewriting_url_join",
                RewritingUrlTableMap::VIEW . " = ?",
                (new Folder())->getRewrittenUrlViewName(),
                null,
                \PDO::PARAM_STR
            )
            ->addJoinCondition("rewriting_url_join", "ISNULL(" . RewritingUrlTableMap::REDIRECTED . ")")
            ->addJoinObject($folderJoin, "folder_join")
            ->addJoinCondition("folder_join", FolderI18nTableMap::LOCALE . " = ?", $locale, null, \PDO::PARAM_STR);

        $query
            ->addAsColumn("folder_ID", FolderTableMap::ID)
            ->addAsColumn("folder_i18n_TITLE", FolderI18nTableMap::TITLE)
            ->addAsColumn("folder_i18n_DESCRIPTION", FolderI18nTableMap::DESCRIPTION)
            ->addAsColumn("folder_i18n_CHAPO", FolderI18nTableMap::CHAPO)
            ->addAsColumn("folder_i18n_POSTSCRIPTUM", FolderI18nTableMap::POSTSCRIPTUM)
            ->addAsColumn("folder_VISIBLE", FolderTableMap::VISIBLE)
            ->addAsColumn("folder_seo_TITLE", FolderI18nTableMap::META_TITLE)
            ->addAsColumn("folder_seo_META_DESCRIPTION", FolderI18nTableMap::META_DESCRIPTION)
            ->addAsColumn("folder_seo_META_KEYWORDS", FolderI18nTableMap::META_KEYWORDS)
            ->addAsColumn("folder_URL", RewritingUrlTableMap::URL)
            ->select([
                "folder_ID",
                "folder_VISIBLE",
                "folder_i18n_TITLE",
                "folder_i18n_DESCRIPTION",
                "folder_i18n_CHAPO",
                "folder_i18n_POSTSCRIPTUM",
                "folder_URL",
                "folder_seo_TITLE",
                "folder_seo_META_DESCRIPTION",
                "folder_seo_META_KEYWORDS",
            ]);

        return $query;
    }

    protected function getDefaultOrder()
    {
        return  [
            "id",
            "visible",
            "folder_title",
            "folder_description",
            "folder_chapo",
            "folder_postscriptum",
            "url",
            "page_title",
            "meta_description",
            "meta_keywords",
        ];
    }

    protected function getAliases()
    {
        return [
            "folder_ID" => "id",
            "folder_VISIBLE" => "visible",
            "folder_i18n_TITLE" => "folder_title",
            "folder_i18n_DESCRIPTION" => "folder_description",
            "folder_i18n_CHAPO" => "folder_chapo",
            "folder_i18n_POSTSCRIPTUM" => "folder_postscriptum",
            "folder_URL" => "url",
            "folder_seo_TITLE" => "page_title",
            "folder_seo_META_DESCRIPTION" => "meta_description",
            "folder_seo_META_KEYWORDS" => "meta_keywords",
        ];
    }
}
