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
use Thelia\Model\Map\BrandI18nTableMap;
use Thelia\Model\Map\BrandTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;
use Thelia\Model\Brand;
use Thelia\Model\Lang;
use Thelia\Model\BrandQuery;

/**
 * Class BrandI18Export
 * @package Thelia\ImportExport\Export\Type
 */
class BrandI18Export extends ExportHandler
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

        $query = BrandQuery::create();

        $urlJoin = new Join(BrandTableMap::ID, RewritingUrlTableMap::VIEW_ID, Criteria::LEFT_JOIN);
        $brandJoin = new Join(BrandTableMap::ID, BrandI18nTableMap::ID, Criteria::LEFT_JOIN);

        $query
            ->addJoinObject($urlJoin, "rewriting_url_join")
            ->addJoinCondition("rewriting_url_join", RewritingUrlTableMap::VIEW_LOCALE . " = ?", $locale, null,
                \PDO::PARAM_STR)
            ->addJoinCondition(
                "rewriting_url_join",
                RewritingUrlTableMap::VIEW . " = ?",
                (new Brand())->getRewrittenUrlViewName(),
                null,
                \PDO::PARAM_STR
            )
            ->addJoinCondition("rewriting_url_join", "ISNULL(" . RewritingUrlTableMap::REDIRECTED . ")")
            ->addJoinObject($brandJoin, "brand_join")
            ->addJoinCondition("brand_join", BrandI18nTableMap::LOCALE . " = ?", $locale, null, \PDO::PARAM_STR);

        $query
            ->addAsColumn("brand_ID", BrandTableMap::ID)
            ->addAsColumn("brand_i18n_TITLE", BrandI18nTableMap::TITLE)
            ->addAsColumn("brand_i18n_DESCRIPTION", BrandI18nTableMap::DESCRIPTION)
            ->addAsColumn("brand_i18n_CHAPO", BrandI18nTableMap::CHAPO)
            ->addAsColumn("brand_i18n_POSTSCRIPTUM", BrandI18nTableMap::POSTSCRIPTUM)
            ->addAsColumn("brand_VISIBLE", BrandTableMap::VISIBLE)
            ->addAsColumn("brand_seo_TITLE", BrandI18nTableMap::META_TITLE)
            ->addAsColumn("brand_seo_META_DESCRIPTION", BrandI18nTableMap::META_DESCRIPTION)
            ->addAsColumn("brand_seo_META_KEYWORDS", BrandI18nTableMap::META_KEYWORDS)
            ->addAsColumn("brand_URL", RewritingUrlTableMap::URL)
            ->select([
                "brand_ID",
                "brand_VISIBLE",
                "brand_i18n_TITLE",
                "brand_i18n_DESCRIPTION",
                "brand_i18n_CHAPO",
                "brand_i18n_POSTSCRIPTUM",
                "brand_URL",
                "brand_seo_TITLE",
                "brand_seo_META_DESCRIPTION",
                "brand_seo_META_KEYWORDS",
            ]);

        return $query;
    }

    protected function getDefaultOrder()
    {
        return  [
            "id",
            "visible",
            "brand_title",
            "brand_description",
            "brand_chapo",
            "brand_postscriptum",
            "url",
            "page_title",
            "meta_description",
            "meta_keywords",
        ];
    }

    protected function getAliases()
    {
        return [
            "brand_ID" => "id",
            "brand_VISIBLE" => "visible",
            "brand_i18n_TITLE" => "brand_title",
            "brand_i18n_DESCRIPTION" => "brand_description",
            "brand_i18n_CHAPO" => "brand_chapo",
            "brand_i18n_POSTSCRIPTUM" => "brand_postscriptum",
            "brand_URL" => "url",
            "brand_seo_TITLE" => "page_title",
            "brand_seo_META_DESCRIPTION" => "meta_description",
            "brand_seo_META_KEYWORDS" => "meta_keywords",
        ];
    }
}
