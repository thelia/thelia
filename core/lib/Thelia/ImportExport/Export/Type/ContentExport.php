<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\ImportExport\Export\Type;

use PDO;
use Propel\Runtime\Propel;
use Thelia\ImportExport\Export\JsonFileAbstractExport;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Content;

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
        'content_id' => 'id',
        'content_i18n_title' => 'title',
        'content_i18n_chapo' => 'chapo',
        'content_i18n_description' => 'description',
        'content_i18n_postscriptum' => 'conclusion',
        'content_visible' => 'visible',
        'content_i18n_meta_title' => 'seo_title',
        'content_i18n_meta_description' => 'seo_description',
        'content_i18n_meta_keywords' => 'seo_keywords',
        'rewriting_url_url' => 'url',
        'content_folder_folder_id' => 'folder_id',
        'content_folder_default_folder' => 'is_default_folder',
        'folder_i18n_title' => 'folder_title'
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
                        content.id as "content_id",
                        content_i18n.title as "content_i18n_title",
                        content_i18n.chapo as "content_i18n_chapo",
                        content_i18n.description as "content_i18n_description",
                        content_i18n.postscriptum as "content_i18n_postscriptum",
                        content.visible as "content_visible",
                        content_i18n.meta_title as "content_i18n_meta_title",
                        content_i18n.meta_description as "content_i18n_meta_description",
                        content_i18n.meta_keywords as "content_i18n_meta_keywords",
                        rewriting_url.url as "rewriting_url_url",
                        content_folder.folder_id as "content_folder_folder_id",
                        content_folder.default_folder as "content_folder_default_folder",
                        folder_i18n.title as "folder_i18n_title"
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

        return $this->getDataJsonCache($stmt, 'content');
    }
}
