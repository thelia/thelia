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
use Thelia\ImportExport\Export\AbstractExport;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\Map\ContentFolderTableMap;
use Thelia\Model\Map\ContentI18nTableMap;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Map\FolderI18nTableMap;
use Thelia\Model\Map\FolderTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;

/**
 * Class ContentExport
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ContentExport extends AbstractExport
{
    const FILE_NAME = 'content';

    const EXPORT_IMAGE = true;

    const EXPORT_DOCUMENT = true;

    const DIRECTORY_NAME = "content";

    protected $orderAndAliases = [
        ContentTableMap::ID => 'id',
        'content_TITLE' => 'title',
        'content_CHAPO' => 'chapo',
        'content_DESCRIPTION' => 'description',
        'content_CONCLUSION' => 'conclusion',
        ContentTableMap::VISIBLE => 'visible',
        'content_seo_TITLE' => 'seo_title',
        'content_seo_DESCRIPTION' => 'seo_description',
        'content_seo_KEYWORDS' => 'seo_keywords',
        'url_URL' => 'url',
        'folder_ID' => 'folder_id',
        'folder_IS_DEFAULT' => 'is_default_folder',
        'folder_TITLE' => 'folder_title'
    ];

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->imagesPaths = [
            THELIA_ROOT . ConfigQuery::read('images_library_path') . DS . 'content'
        ];

        $this->documentsPaths = [
            THELIA_ROOT . ConfigQuery::read('documents_library_path') . DS . 'content'
        ];
    }

    public function getData()
    {
        $locale = $this->language->getLocale();

        $contentI18nJoin = new Join(ContentTableMap::ID, ContentI18nTableMap::ID, Criteria::LEFT_JOIN);
        $folderI18nJoin = new Join(FolderTableMap::ID, FolderI18nTableMap::ID, Criteria::LEFT_JOIN);
        $urlJoin = new Join(ContentTableMap::ID, RewritingUrlTableMap::VIEW_ID, Criteria::LEFT_JOIN);

        $query = ContentQuery::create()
            ->addSelfSelectColumns()
            ->useContentFolderQuery(null, Criteria::LEFT_JOIN)
                ->useFolderQuery(null, Criteria::LEFT_JOIN)
                    ->addJoinObject($folderI18nJoin, "folder_i18n_join")
                    ->addJoinCondition("folder_i18n_join", FolderI18nTableMap::LOCALE . " = ?", $locale, null, \PDO::PARAM_STR)
                    ->addAsColumn("folder_TITLE", FolderI18nTableMap::TITLE)
                    ->addAsColumn("folder_ID", FolderTableMap::ID)
                ->endUse()
                ->addAsColumn("folder_IS_DEFAULT", ContentFolderTableMap::DEFAULT_FOLDER)
            ->endUse()
            ->addJoinObject($contentI18nJoin, "content_i18n_join")
            ->addJoinCondition("content_i18n_join", ContentI18nTableMap::LOCALE . " = ?", $locale, null, \PDO::PARAM_STR)
            ->addAsColumn("content_TITLE", ContentI18nTableMap::TITLE)
            ->addAsColumn("content_CHAPO", ContentI18nTableMap::CHAPO)
            ->addAsColumn("content_DESCRIPTION", ContentI18nTableMap::DESCRIPTION)
            ->addAsColumn("content_CONCLUSION", ContentI18nTableMap::POSTSCRIPTUM)
            ->addAsColumn("content_seo_TITLE", ContentI18nTableMap::META_TITLE)
            ->addAsColumn("content_seo_DESCRIPTION", ContentI18nTableMap::META_DESCRIPTION)
            ->addAsColumn("content_seo_KEYWORDS", ContentI18nTableMap::META_KEYWORDS)
            ->addJoinObject($urlJoin, "url_rewriting_join")
            ->addJoinCondition(
                "url_rewriting_join",
                RewritingUrlTableMap::VIEW . " = ?",
                (new Content())->getRewrittenUrlViewName(),
                null,
                \PDO::PARAM_STR
            )
            ->addJoinCondition(
                "url_rewriting_join",
                RewritingUrlTableMap::VIEW_LOCALE . " = ?",
                $locale,
                null,
                \PDO::PARAM_STR
            )
            ->addAsColumn("url_URL", RewritingUrlTableMap::URL)
            ->groupBy(ContentTableMap::ID)
            ->groupBy("folder_ID")
            ->orderById()
        ;

        return $query;
    }
}
