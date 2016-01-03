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

namespace Thelia\Tests\Controller;

use Thelia\Controller\Admin\ImportController;
use Thelia\Core\FileFormat\Archive\ArchiveBuilder\ZipArchiveBuilder;
use Thelia\Core\FileFormat\Archive\ArchiveBuilderManagerTrait;
use Thelia\Core\FileFormat\Formatting\Formatter\XMLFormatter;
use Thelia\Core\FileFormat\Formatting\FormatterManagerTrait;
use Thelia\Core\FileFormat\FormatType;

/**
 * Class ImportControllerTrait
 * @package Thelia\Tests\Controller
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ImportControllerTest extends ControllerTestBase
{
    use FormatterManagerTrait;
    use ArchiveBuilderManagerTrait;
    use ImportExportControllerTrait;

    /**
     * @return \Thelia\Controller\BaseController The controller you want to test
     */
    protected function getController()
    {
        return new ImportController();
    }

    public function testCheckFileExtension()
    {
        $this->controller->checkFileExtension("a.zip", "zip");
    }

    /**
     * @expectedException \Thelia\Form\Exception\FormValidationException
     * @expectedExceptionMessage The extension ".zip" is not allowed
     */
    public function testCheckFileExtensionFail()
    {
        $this->controller->checkFileExtension("a.zip", null);
    }

    /**
     * @expectedException \Thelia\Form\Exception\FormValidationException
     * @expectedExceptionMessage The extension ".bz2" is not allowed
     */
    public function testCheckFileExtensionFailMultipleExt()
    {
        $this->controller->checkFileExtension("a.tar.bz2", null);
    }

    /**
     * @expectedException \Thelia\Form\Exception\FormValidationException
     * @expectedExceptionMessage The extension "" is not allowed
     */
    public function testCheckFileExtensionFailNoExt()
    {
        $this->controller->checkFileExtension("file", null);
    }

    public function testGetFileContentInArchive()
    {
        /** @var ZipArchiveBuilder $archive */
        $archive = $this->getArchiveBuilderManager($this->container)->get("ZIP");
        $formatter = new XMLFormatter();

        $archive->addFileFromString("foo", $formatter::FILENAME . "." . $formatter->getExtension());

        $content = $this->controller->getFileContentInArchive(
            $archive,
            $this->getFormatterManager($this->container),
            [$formatter->getHandledType()]
        );

        $this->assertEquals(
            [
                "content" => "foo",
                "formatter" => $formatter
            ],
            $content
        );
    }

    /**
     * @expectedException \Thelia\Exception\FileNotFoundException
     * @expectedExceptionMessage Your archive must contain one of these file and doesn't:
     */
    public function testGetFileContentInArchiveFail()
    {
        /** @var ZipArchiveBuilder $archive */
        $archive = $this->getArchiveBuilderManager($this->container)->get("ZIP");
        $formatter = new XMLFormatter();

        $archive->addFileFromString("foo", "bar");

        $this->controller->getFileContentInArchive(
            $archive,
            $this->getFormatterManager($this->container),
            [$formatter->getHandledType()]
        );
    }

    public function testRetrieveFormatTools()
    {
        $handler = $this
            ->getMock(
                "\\Thelia\\ImportExport\\Import\\ImportHandler",
                [
                    "getMandatoryColumns",
                    "retrieveFromFormatterData",
                    "getHandledTypes"
                ],
                [
                    $this->container
                ]
            )
        ;

        $handler
            ->expects($this->any())
                ->method("getHandledTypes")
                ->willReturn(FormatType::UNBOUNDED)
        ;

        $tools = $this->controller->retrieveFormatTools(
            "foo.xml",
            $handler,
            $this->getFormatterManager($this->container),
            $this->getArchiveBuilderManager($this->container)
        );

        $this->assertArrayHasKey("formatter", $tools);
        $this->assertInstanceOf(
            "Thelia\\Core\\FileFormat\\Formatting\\AbstractFormatter",
            $tools["formatter"]
        );

        $this->assertArrayHasKey("archive_builder", $tools);
        $this->assertNull($tools["archive_builder"]);

        $this->assertArrayHasKey("extension", $tools);
        $this->assertEquals(".xml", $tools["extension"]);

        $this->assertArrayHasKey("types", $tools);
        $this->assertEquals(
            FormatType::UNBOUNDED,
            $tools["types"]
        );

        $handler = $this
            ->getMock(
                "\\Thelia\\ImportExport\\Import\\ImportHandler",
                [
                    "getMandatoryColumns",
                    "retrieveFromFormatterData",
                    "getHandledTypes"
                ],
                [
                    $this->container
                ]
            )
        ;

        $handler
            ->expects($this->any())
            ->method("getHandledTypes")
            ->willReturn([FormatType::UNBOUNDED])
        ;

        $tools = $this->controller->retrieveFormatTools(
            "foo.zip",
            $handler,
            $this->getFormatterManager($this->container),
            $this->getArchiveBuilderManager($this->container)
        );

        $this->assertArrayHasKey("formatter", $tools);
        $this->assertNull($tools["formatter"]);

        $this->assertArrayHasKey("archive_builder", $tools);
        $this->assertInstanceOf(
            "Thelia\\Core\\FileFormat\\Archive\\AbstractArchiveBuilder",
            $tools["archive_builder"]
        );

        $this->assertArrayHasKey("extension", $tools);
        $this->assertEquals(".zip", $tools["extension"]);

        $this->assertArrayHasKey("types", $tools);
        $this->assertEquals(
            [FormatType::UNBOUNDED],
            $tools["types"]
        );
    }

    /**
     * @expectedException \Thelia\Form\Exception\FormValidationException
     */
    public function testRetrieveFormatToolsFail()
    {
        $handler = $this
            ->getMock(
                "\\Thelia\\ImportExport\\Import\\ImportHandler",
                [
                    "getMandatoryColumns",
                    "retrieveFromFormatterData",
                    "getHandledTypes"
                ],
                [
                    $this->container
                ]
            )
        ;

        $handler
            ->expects($this->any())
            ->method("getHandledTypes")
            ->willReturn(FormatType::UNBOUNDED)
        ;

        $this->controller->retrieveFormatTools(
            "foo.csv",
            $handler,
            $this->getFormatterManager($this->container),
            $this->getArchiveBuilderManager($this->container)
        );
    }
}
