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
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\ContentImageQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderImageQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\Lang;
use Thelia\Model\Map\ContentImageTableMap;
use Thelia\Model\Map\FolderImageTableMap;
use Thelia\Model\Map\FolderTableMap;

/**
 * Class ContentExportTest
 * @package Thelia\Tests\ImportExport\Export
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ContentExportTest extends \PHPUnit_Framework_TestCase
{
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

                    $this->assertEquals(
                        $folder->getTitle(),
                        $data[$i]["folder_title"]
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
                        $data[$i]["content_images"] = $data[$i-$j-1]["content_images"];
                        break;
                    }
                }
            }

            $this->assertEquals($imagesString, $data[$i]["content_images"]);

            $folderImages = FolderImageQuery::create()
                ->useFolderQuery()
                    ->useContentFolderQuery()
                        ->filterByContentId($data[$i]["id"])
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
} 