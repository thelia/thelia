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

namespace Thelia\Tests\ImportExport\Export;

use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Export\Type\ContentExport;
use Thelia\Model\ContentDocumentQuery;
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\ContentImageQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderDocumentQuery;
use Thelia\Model\FolderImageQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\Lang;
use Thelia\Model\Map\ContentDocumentTableMap;
use Thelia\Model\Map\ContentImageTableMap;
use Thelia\Model\Map\FolderDocumentTableMap;
use Thelia\Model\Map\FolderImageTableMap;

/**
 * Class ContentExportTest
 * @package Thelia\Tests\ImportExport\Export
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ContentExportTest extends \PHPUnit_Framework_TestCase
{
    /** @var Lang */
    protected $lang;

    /** @var ContentExport */
    protected $handler;

    public function setUp()
    {
        new Translator(new Container());

        $this->lang = Lang::getDefaultLanguage();
        $this->handler = new ContentExport(new Container());
    }

    public function testQuery()
    {
        $data = $this->handler->buildData($this->lang)->getData();

        $max = count($data);
        if ($max > 50) {
            $max = 50;
        }

        for ($i = 0; $i < $max;) {
            $content = ContentQuery::create()->findPk($data[$i]["id"]);

            $this->assertNotNull($content);

            $content->setLocale($this->lang->getLocale());

            $this->assertEquals($content->getTitle(), $data[$i]["title"]);
            $this->assertEquals($content->getDescription(), $data[$i]["description"]);
            $this->assertEquals($content->getChapo(), $data[$i]["chapo"]);
            $this->assertEquals($content->getPostscriptum(), $data[$i]["conclusion"]);
            $this->assertEquals($content->getMetaTitle(), $data[$i]["seo_title"]);
            $this->assertEquals($content->getMetaDescription(), $data[$i]["seo_description"]);
            $this->assertEquals($content->getMetaKeywords(), $data[$i]["seo_keywords"]);

            do {
                if (null !== $data[$i]["folder_id"]) {
                    $folder = FolderQuery::create()->findPk($data[$i]["folder_id"]);

                    $this->assertNotNull($folder);

                    $contentFolder = ContentFolderQuery::create()
                        ->filterByContent($content)
                        ->filterByFolder($folder)
                        ->findOne()
                    ;

                    $this->assertNotNull($contentFolder);

                    $folder->setLocale($this->lang->getLocale());

                    $this->assertEquals(
                        $folder->getTitle(),
                        $data[$i]["folder_title"]
                    );

                    $this->assertEquals(
                        $contentFolder->getDefaultFolder(),
                        (bool) ((int) $data[$i]["is_default_folder"])
                    );
                }
            } while (
                isset($data[++$i]["id"]) &&
                $data[$i-1]["id"] === $data[$i]["id"] &&
                ++$max
            );
        }
    }

    public function testQueryImages()
    {
        $data = $this->handler
            ->setImageExport(true)
            ->buildData($this->lang)
            ->getData()
        ;

        $max = count($data);
        if ($max > 50) {
            $max = 50;
        }

        for ($i = 0; $i < $max; ++$i) {
            $images = ContentImageQuery::create()
                ->filterByContentId($data[$i]["id"])
                ->select(ContentImageTableMap::FILE)
                ->find()
                ->toArray()
            ;

            $imagesString = implode(",", $images);

            if (empty($data[$i]["content_images"])) {
                $j = 1;
                while ($data[$i-$j]["id"] === $data[$i]["id"]) {
                    if (!empty($data[$i - $j++]["content_images"])) {
                        $data[$i]["content_images"] = $data[$i-$j+1]["content_images"];
                        break;
                    }
                }
            }

            $this->assertEquals($imagesString, $data[$i]["content_images"]);

            $folderImages = FolderImageQuery::create()
                ->useFolderQuery()
                    ->useContentFolderQuery()
                        ->filterByContentId($data[$i]["id"])
                        ->filterByFolderId($data[$i]["folder_id"])
                    ->endUse()
                ->endUse()
                ->select(FolderImageTableMap::FILE)
                ->find()
                ->toArray()
            ;

            $folderImages = implode(",", $folderImages);

            $this->assertEquals($folderImages, $data[$i]["folder_images"]);
        }
    }

    public function testQueryDocument()
    {
        $data = $this->handler
            ->setDocumentExport(true)
            ->buildData($this->lang)
            ->getData()
        ;

        $max = count($data);
        if ($max > 50) {
            $max = 50;
        }

        for ($i = 0; $i < $max; ++$i) {
            $documents = ContentDocumentQuery::create()
                ->filterByContentId($data[$i]["id"])
                ->select(ContentDocumentTableMap::FILE)
                ->find()
                ->toArray()
            ;

            $documentsString = implode(",", $documents);

            if (empty($data[$i]["content_documents"])) {
                $j = 1;
                while ($data[$i-$j]["id"] === $data[$i]["id"]) {
                    if (!empty($data[$i - $j++]["content_documents"])) {
                        $data[$i]["content_documents"] = $data[$i-$j+1]["content_documents"];
                        break;
                    }
                }
            }

            $this->assertEquals($documentsString, $data[$i]["content_documents"]);

            $folderDocuments = FolderDocumentQuery::create()
                ->useFolderQuery()
                    ->useContentFolderQuery()
                        ->filterByContentId($data[$i]["id"])
                        ->filterByFolderId($data[$i]["folder_id"])
                    ->endUse()
                ->endUse()
                ->select(FolderDocumentTableMap::FILE)
                ->find()
                ->toArray()
            ;

            $folderDocuments = implode(",", $folderDocuments);

            $this->assertEquals($folderDocuments, $data[$i]["folder_documents"]);
        }
    }
}
