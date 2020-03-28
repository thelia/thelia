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

use PDO;
use Propel\Runtime\Propel;
use Thelia\ImportExport\Export\JsonFileAbstractExport;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Content;
use Thelia\Model\Map\ContentFolderTableMap;
use Thelia\Model\Map\ContentI18nTableMap;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Map\FolderI18nTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;

/**
 * Class ContentExport
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 * @author Florian Bernard <fbernard@openstudio.fr>
 */
class ContentExport extends JsonFileAbstractExport
{
    const FILE_NAME = 'content';

    const EXPORT_IMAGE = true;

    const EXPORT_DOCUMENT = true;

    const DIRECTORY_NAME = "content";

    protected $orderAndAliases = [
        ContentTableMap::COL_ID => 'id',
        ContentI18nTableMap::COL_TITLE => 'title',
        ContentI18nTableMap::COL_CHAPO => 'chapo',
        ContentI18nTableMap::COL_DESCRIPTION => 'description',
        ContentI18nTableMap::COL_POSTSCRIPTUM => 'conclusion',
        ContentTableMap::COL_VISIBLE => 'visible',
        ContentI18nTableMap::COL_META_TITLE => 'seo_title',
        ContentI18nTableMap::COL_META_DESCRIPTION => 'seo_description',
        ContentI18nTableMap::COL_META_KEYWORDS => 'seo_keywords',
        RewritingUrlTableMap::COL_URL => 'url',
        ContentFolderTableMap::COL_FOLDER_ID => 'folder_id',
        ContentFolderTableMap::COL_DEFAULT_FOLDER => 'is_default_folder',
        FolderI18nTableMap::COL_TITLE=> 'folder_title'
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

        $con = Propel::getConnection();
        $query = 'SELECT 
                        content.id as "content.id", 
                        content_i18n.title as "content_i18n.title",
                        content_i18n.chapo as "content_i18n.chapo",
                        content_i18n.description as "content_i18n.description",
                        content_i18n.postscriptum as "content_i18n.postscriptum",
                        content.visible as "content.visible",
                        content_i18n.meta_title as "content_i18n.meta_title",
                        content_i18n.meta_description as "content_i18n.meta_description",
                        content_i18n.meta_keywords as "content_i18n.meta_keywords",
                        rewriting_url.url as "rewriting_url.url",
                        content_folder.folder_id as "content_folder.folder_id",
                        content_folder.default_folder as "content_folder.default_folder",
                        folder_i18n.title as "folder_i18n.title"
                    FROM content
                    LEFT JOIN content_i18n ON content_i18n.id = content.id AND content_i18n.locale = :locale
                    LEFT JOIN content_folder ON content_folder.content_id = content.id
                    LEFT JOIN folder_i18n ON folder_i18n.id = content_folder.folder_id AND folder_i18n.locale = :locale
                    LEFT JOIN rewriting_url ON rewriting_url.view = "'.(new Content())->getRewrittenUrlViewName().'" AND rewriting_url.view_id = content.id
                    GROUP BY content.id'
        ;
        $stmt = $con->prepare($query);
        $stmt->bindValue('locale', $locale);
        $stmt->execute();

        $filename = THELIA_CACHE_DIR . '/export/' . 'content.json';

        if (file_exists($filename)) {
            unlink($filename);
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            file_put_contents($filename, json_encode($row) . "\r\n", FILE_APPEND);
        }

        return $filename;
    }
}
