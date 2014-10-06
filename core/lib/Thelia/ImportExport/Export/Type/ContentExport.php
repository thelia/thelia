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
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\FileFormat\FormatType;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\ImportExport\Export\DocumentsExportInterface;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\ImportExport\Export\ImagesExportInterface;
use Thelia\Model\Content;
use Thelia\Model\ContentDocumentI18nQuery;
use Thelia\Model\ContentDocumentQuery;
use Thelia\Model\ContentImageQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderDocumentQuery;
use Thelia\Model\FolderImageQuery;
use Thelia\Model\Lang;
use Thelia\Model\Map\ContentDocumentTableMap;
use Thelia\Model\Map\ContentFolderTableMap;
use Thelia\Model\Map\ContentI18nTableMap;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Map\FolderDocumentTableMap;
use Thelia\Model\Map\FolderI18nTableMap;
use Thelia\Model\Map\FolderImageTableMap;
use Thelia\Model\Map\FolderTableMap;
use Thelia\Model\Map\RewritingUrlTableMap;

/**
 * Class ContentExport
 * @package Thelia\ImportExport\Export\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ContentExport extends ExportHandler implements
    ImagesExportInterface,
    DocumentsExportInterface
{
    const DIRECTORY_NAME = "content";

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
     * @return ContentQuery
     */
    public function getQuery(Lang $lang)
    {
        $locale = $lang->getLocale();

        $contentI18nJoin = new Join(ContentTableMap::ID, ContentI18nTableMap::ID, Criteria::LEFT_JOIN);
        $folderI18nJoin = new Join(FolderTableMap::ID, FolderI18nTableMap::ID, Criteria::LEFT_JOIN);
        $urlJoin = new Join(ContentTableMap::ID, RewritingUrlTableMap::VIEW_ID, Criteria::LEFT_JOIN);


        $query = ContentQuery::create()
            ->select([
                ContentTableMap::ID,
                ContentTableMap::VISIBLE,
                "content_TITLE",
                "content_CHAPO",
                "content_DESCRIPTION",
                "content_CONCLUSION",
                "content_seo_TITLE",
                "content_seo_DESCRIPTION",
                "content_seo_KEYWORDS",
                "url_URL",
                "folder_TITLE",
                "folder_ID",
                "folder_IS_DEFAULT"
            ])
            ->_if($this->isImageExport())
                ->useContentImageQuery("content_image_join", Criteria::LEFT_JOIN)
                    ->addAsColumn("content_IMAGES", "GROUP_CONCAT(DISTINCT `content_image_join`.FILE)")
                    ->addSelectColumn("content_IMAGES")
                    ->groupByContentId()
                ->endUse()
            ->_endif()
            ->_if($this->isDocumentExport())
                ->useContentDocumentQuery("content_document_join", Criteria::LEFT_JOIN)
                    ->addAsColumn("content_DOCUMENTS", "GROUP_CONCAT(DISTINCT `content_document_join`.FILE)")
                    ->addSelectColumn("content_DOCUMENTS")
                    ->groupByContentId()
                ->endUse()
            ->_endif()
            ->useContentFolderQuery(null, Criteria::LEFT_JOIN)
                ->useFolderQuery(null, Criteria::LEFT_JOIN)
                    ->_if($this->isDocumentExport())
                        ->useFolderDocumentQuery(null, Criteria::LEFT_JOIN)
                            ->addAsColumn("folder_DOCUMENTS", "GROUP_CONCAT(DISTINCT ".FolderDocumentTableMap::FILE.")")
                            ->addSelectColumn("folder_DOCUMENTS")
                        ->endUse()
                    ->_endif()
                    ->_if($this->isImageExport())
                        ->useFolderImageQuery(null, Criteria::LEFT_JOIN)
                            ->addAsColumn("folder_IMAGES", "GROUP_CONCAT(DISTINCT ".FolderImageTableMap::FILE.")")
                            ->addSelectColumn("folder_IMAGES")
                        ->endUse()
                    ->_endif()
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

    /**
     * @param  Lang $lang
     * @return ModelCriteria|array|BaseLoop
     */
    public function buildDataSet(Lang $lang)
    {
        $query = $this->getQuery($lang);

        $dataSet = $query
            ->find()
            ->toArray()
        ;

        $previous = null;
        foreach ($dataSet as &$line) {
            if ($previous === null || $previous !== $line[ContentTableMap::ID]) {
                $previous = $line[ContentTableMap::ID];
            } else {
                /**
                 * Do not repeat content values
                 */
                $line["content_TITLE"] = "";
                $line[ContentTableMap::VISIBLE] = "";
                $line["content_CHAPO"] = "";
                $line["content_DESCRIPTION"] = "";
                $line["content_CONCLUSION"] = "";
                $line["content_seo_TITLE"] = "";
                $line["content_seo_DESCRIPTION"] = "";
                $line["content_seo_KEYWORDS"] = "";
                $line["url_URL"] = "";
                $line["content_IMAGES"] = "";
                $line["content_DOCUMENTS"] = "";

                if (isset($line["content_IMAGES"])) {
                    $line["content_IMAGES"] = "";
                }

                if (isset($line["content_DOCUMENTS"])) {
                    $line["content_DOCUMENTS"] = "";
                }
            }
        }

        return $dataSet;
    }

    protected function getDefaultOrder()
    {
        return [
            "id",
            "title",
            "chapo",
            "description",
            "conclusion",
            "visible",
            "seo_title",
            "seo_description",
            "seo_keywords",
            "url",
            "folder_id",
            "is_default_folder",
            "folder_title",
        ];
    }

    protected function getAliases()
    {
        return [
            ContentTableMap::ID => "id",
            ContentTableMap::VISIBLE => "visible",
            "content_TITLE" => "title",
            "content_CHAPO" => "chapo",
            "content_DESCRIPTION" => "description",
            "content_CONCLUSION" => "conclusion",
            "content_seo_TITLE" => "seo_title",
            "content_seo_DESCRIPTION" => "seo_description",
            "content_seo_KEYWORDS" => "seo_keywords",
            "url_URL" => "url",
            "folder_TITLE" => "folder_title",
            "folder_ID" => "folder_id",
            "folder_IS_DEFAULT" => "is_default_folder"
        ];
    }


    /**
     * @return array
     *
     * return an array with the paths to the documents to include in the archive
     */
    public function getDocumentsPaths()
    {
        $documentPaths = [];

        $folderDocuments = FolderDocumentQuery::create()
            ->find();
        /** @var \Thelia\Model\FolderDocument $folderDocument */
        foreach ($folderDocuments as $folderDocument) {
            $this->addFileToArray($folderDocument, $documentPaths);

        }

        $contentDocuments = ContentDocumentQuery::create()
            ->find();

        /** @var \Thelia\Model\ContentDocument $contentDocument */
        foreach ($contentDocuments as $contentDocument) {
            $this->addFileToArray($contentDocument, $documentPaths);
        }

        return $documentPaths;
    }

    /**
     * @return array
     *
     * return an array with the paths to the images to include in the archive
     */
    public function getImagesPaths()
    {
        $imagePaths = [];

        $folderImages = FolderImageQuery::create()
            ->find();

        /** @var \Thelia\Model\FolderDocument $folderImage */
        foreach ($folderImages as $folderImage) {
            $this->addFileToArray($folderImage, $imagePaths);
        }

        $contentImages = ContentImageQuery::create()
            ->find();

        /** @var \Thelia\Model\ContentImage $contentImage */
        foreach ($contentImages as $contentImage) {
            $this->addFileToArray($contentImage, $imagePaths);
        }

        return $imagePaths;
    }
}
