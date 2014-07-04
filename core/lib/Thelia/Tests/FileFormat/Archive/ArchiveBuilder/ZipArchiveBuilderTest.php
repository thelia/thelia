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

namespace Thelia\Tests\FileFormat\Archive\ArchiveBuilder;
use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\FileFormat\Archive\ArchiveBuilder\ZipArchiveBuilder;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Tests\Tools\FakeFileDownloader;

/**
 * Class ZipArchiveBuilderTest
 * @package Thelia\Tests\FileFormat\Archive\ArchiveBuilder
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ZipArchiveBuilderTest extends \PHPUnit_Framework_TestCase
{
    /** @var  ZipArchiveBuilder */
    protected $zip;

    /** @var  ZipArchiveBuilder */
    protected $loadedZip;

    public function setUp()
    {
        new Translator(
            new Container()
        );

        Tlog::getNewInstance();

        $this->zip = new ZipArchiveBuilder();

<<<<<<< HEAD
<<<<<<< HEAD
        $this->zip->setEnvironment("dev");

        $this->loadedZip = $this->zip->loadArchive(
            __DIR__ . DS . "TestResources/well_formatted.zip"
=======
        $this->loadedZip = $this->zip->loadArchive(
            __DIR__ . DS . "TestResources/well_formatted.zip",
            "dev"
>>>>>>> Define archive builders and formatters
=======
        $this->zip->setEnvironment("dev");

        $this->loadedZip = $this->zip->loadArchive(
            __DIR__ . DS . "TestResources/well_formatted.zip"
>>>>>>> Finish tar, tar.gz, tar.bz2 and tests
        );
    }

    /**
     * This method formats a path to be compatible with \ZipArchive
<<<<<<< HEAD
<<<<<<< HEAD
     */
    public function testFormatFilePath()
    {
        $this->assertEquals(
            "foo",
            $this->zip->formatFilePath("foo")
=======
     *
     *
=======
>>>>>>> Begin tar, tar.bz2 and tar.gz formatter, fix zip test resources
     */
    public function testFormatFilePath()
    {
        $this->assertEquals(
            "foo",
<<<<<<< HEAD
            $this->zip->getFilePath("foo")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatFilePath("foo")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "foo",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatFilePath("/foo")
=======
            $this->zip->getFilePath("/foo")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatFilePath("/foo")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "foo",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatFilePath("foo/")
=======
            $this->zip->getFilePath("foo/")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatFilePath("foo/")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "foo",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatFilePath("/foo/")
=======
            $this->zip->getFilePath("/foo/")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatFilePath("/foo/")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "/foo/bar",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatFilePath("foo/bar")
=======
            $this->zip->getFilePath("foo/bar")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatFilePath("foo/bar")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "/foo/bar",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatFilePath("/foo/bar")
=======
            $this->zip->getFilePath("/foo/bar")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatFilePath("/foo/bar")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "/foo/bar",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatFilePath("/foo//bar/")
=======
            $this->zip->getFilePath("/foo//bar/")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatFilePath("/foo//bar/")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "/foo/bar",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatFilePath("/foo/bar/")
=======
            $this->zip->getFilePath("/foo/bar/")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatFilePath("/foo/bar/")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "/foo/bar/baz",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatFilePath("foo/bar/baz")
=======
            $this->zip->getFilePath("foo/bar/baz")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatFilePath("foo/bar/baz")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "/foo/bar/baz",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatFilePath("//foo/bar///baz/")
        );
    }

    public function testFormatDirectoryPath()
<<<<<<< HEAD
    {
        $this->assertEquals(
            "/foo/",
            $this->zip->formatDirectoryPath("foo")
=======
            $this->zip->getFilePath("//foo/bar///baz/")
=======
            $this->zip->formatFilePath("//foo/bar///baz/")
>>>>>>> Finish implementing and testing zip
        );
    }

    public function testGetDirectoryPath()
=======
>>>>>>> Finish Tar archive builder
    {
        $this->assertEquals(
            "/foo/",
<<<<<<< HEAD
            $this->zip->getDirectoryPath("foo")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatDirectoryPath("foo")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "/foo/",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatDirectoryPath("/foo")
=======
            $this->zip->getDirectoryPath("/foo")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatDirectoryPath("/foo")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "/foo/",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatDirectoryPath("foo/")
=======
            $this->zip->getDirectoryPath("foo/")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatDirectoryPath("foo/")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "/foo/",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatDirectoryPath("/foo/")
=======
            $this->zip->getDirectoryPath("/foo/")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatDirectoryPath("/foo/")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "/foo/bar/",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatDirectoryPath("foo/bar")
=======
            $this->zip->getDirectoryPath("foo/bar")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatDirectoryPath("foo/bar")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "/foo/bar/",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatDirectoryPath("/foo/bar")
=======
            $this->zip->getDirectoryPath("/foo/bar")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatDirectoryPath("/foo/bar")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "/foo/bar/",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatDirectoryPath("/foo//bar/")
=======
            $this->zip->getDirectoryPath("/foo//bar/")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatDirectoryPath("/foo//bar/")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "/foo/bar/",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatDirectoryPath("/foo/bar/")
=======
            $this->zip->getDirectoryPath("/foo/bar/")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatDirectoryPath("/foo/bar/")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "/foo/bar/baz/",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatDirectoryPath("foo/bar/baz")
=======
            $this->zip->getDirectoryPath("foo/bar/baz")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatDirectoryPath("foo/bar/baz")
>>>>>>> Finish implementing and testing zip
        );

        $this->assertEquals(
            "/foo/bar/baz/",
<<<<<<< HEAD
<<<<<<< HEAD
            $this->zip->formatDirectoryPath("//foo/bar///baz/")
=======
            $this->zip->getDirectoryPath("//foo/bar///baz/")
>>>>>>> Define archive builders and formatters
=======
            $this->zip->formatDirectoryPath("//foo/bar///baz/")
>>>>>>> Finish implementing and testing zip
        );
    }

    public function testLoadValidZip()
    {
        $loadedZip = $this->zip->loadArchive(
<<<<<<< HEAD
<<<<<<< HEAD
            __DIR__ . DS . "TestResources/well_formatted.zip"
=======
            __DIR__ . DS . "TestResources/well_formatted.zip",
            "dev"
>>>>>>> Define archive builders and formatters
=======
            __DIR__ . DS . "TestResources/well_formatted.zip"
>>>>>>> Finish tar, tar.gz, tar.bz2 and tests
        );

        $this->assertInstanceOf(
            get_class($this->loadedZip),
            $loadedZip
        );
    }

    /**
<<<<<<< HEAD
<<<<<<< HEAD
     * @expectedException \Thelia\Core\FileFormat\Archive\ArchiveBuilder\Exception\ZipArchiveException
=======
     * @expectedException \Thelia\Core\FileFormat\Archive\ArchiveBuilder\ZipArchiveException
>>>>>>> Define archive builders and formatters
=======
     * @expectedException \Thelia\Core\FileFormat\Archive\ArchiveBuilder\Exception\ZipArchiveException
>>>>>>> Finish implementing and testing zip
     * @expectedExceptionMessage [Zip Error] The file is not a zip archive
     */
    public function testLoadNotValidZip()
    {
        $this->zip->loadArchive(
<<<<<<< HEAD
<<<<<<< HEAD
            __DIR__ . DS . "TestResources/bad_formatted.zip"
=======
            __DIR__ . DS . "TestResources/bad_formatted.zip",
            "dev"
>>>>>>> Define archive builders and formatters
=======
            __DIR__ . DS . "TestResources/bad_formatted.zip"
>>>>>>> Finish tar, tar.gz, tar.bz2 and tests
        );
    }

    /**
     * @expectedException \Thelia\Exception\FileNotFoundException
     */
    public function testLoadNotExistingFile()
    {
        $this->zip->loadArchive(
<<<<<<< HEAD
<<<<<<< HEAD
            __DIR__ . DS . "TestResources/this_file_doesn_t_exist.zip"
=======
            __DIR__ . DS . "TestResources/this_file_doesn_t_exist.zip",
            "dev"
>>>>>>> Define archive builders and formatters
=======
            __DIR__ . DS . "TestResources/this_file_doesn_t_exist.zip"
>>>>>>> Finish tar, tar.gz, tar.bz2 and tests
        );
    }

    public function testLoadOnlineAvailableAndValidFile()
    {
<<<<<<< HEAD
<<<<<<< HEAD
        $this->zip->setFileDownloader(FakeFileDownloader::getInstance());

        $this->zip->loadArchive(
            __DIR__ . DS . "TestResources/well_formatted.zip",
            true
=======
        $this->zip->loadArchive(
            __DIR__ . DS . "TestResources/well_formatted.zip",
            "dev",
            true,
            FakeFileDownloader::getInstance()
>>>>>>> Define archive builders and formatters
=======
        $this->zip->setFileDownloader(FakeFileDownloader::getInstance());

        $this->zip->loadArchive(
            __DIR__ . DS . "TestResources/well_formatted.zip",
            true
>>>>>>> Finish tar, tar.gz, tar.bz2 and tests
        );
    }

    /**
<<<<<<< HEAD
<<<<<<< HEAD
     * @expectedException \Thelia\Core\FileFormat\Archive\ArchiveBuilder\Exception\ZipArchiveException
=======
     * @expectedException \Thelia\Core\FileFormat\Archive\ArchiveBuilder\ZipArchiveException
>>>>>>> Define archive builders and formatters
=======
     * @expectedException \Thelia\Core\FileFormat\Archive\ArchiveBuilder\Exception\ZipArchiveException
>>>>>>> Finish implementing and testing zip
     * @expectedExceptionMessage [Zip Error] The file is not a zip archive
     */
    public function testLoadOnlineAvailableAndNotValidFile()
    {
<<<<<<< HEAD
<<<<<<< HEAD
        $this->zip->setFileDownloader(FakeFileDownloader::getInstance());

        $this->zip->loadArchive(
            __DIR__ . DS . "TestResources/bad_formatted.zip",
            true
=======
        $this->zip->loadArchive(
            __DIR__ . DS . "TestResources/bad_formatted.zip",
            "dev",
            true,
            FakeFileDownloader::getInstance()
>>>>>>> Define archive builders and formatters
=======
        $this->zip->setFileDownloader(FakeFileDownloader::getInstance());

        $this->zip->loadArchive(
            __DIR__ . DS . "TestResources/bad_formatted.zip",
            true
>>>>>>> Finish tar, tar.gz, tar.bz2 and tests
        );
    }

    /**
     * @expectedException \Thelia\Exception\FileNotFoundException
     */
    public function testLoadOnlineNotExistingFile()
    {
<<<<<<< HEAD
<<<<<<< HEAD
        $this->zip->setFileDownloader(FakeFileDownloader::getInstance());

        $this->zip->loadArchive(
            __DIR__ . DS . "TestResources/this_file_doesn_t_exist.zip",
            true
=======
        $this->zip->loadArchive(
            __DIR__ . DS . "TestResources/this_file_doesn_t_exist.zip",
            "dev",
            true,
            FakeFileDownloader::getInstance()
>>>>>>> Define archive builders and formatters
=======
        $this->zip->setFileDownloader(FakeFileDownloader::getInstance());

        $this->zip->loadArchive(
            __DIR__ . DS . "TestResources/this_file_doesn_t_exist.zip",
            true
>>>>>>> Finish tar, tar.gz, tar.bz2 and tests
        );
    }

    public function testHasFile()
    {
        $this->assertTrue(
            $this->loadedZip->hasFile("LICENSE.txt")
        );

        $this->assertFalse(
            $this->loadedZip->hasFile("foo")
        );

        $this->assertFalse(
            $this->loadedZip->hasFile("LICENSE.TXT")
        );
    }

    public function testDeleteFile()
    {
        $this->assertInstanceOf(
            get_class($this->loadedZip),
            $this->loadedZip->deleteFile("LICENSE.txt")
        );
    }

    /**
     * @expectedException \Thelia\Exception\FileNotFoundException
     */
    public function testDeleteNotExistingFile()
    {
        $this->loadedZip->deleteFile("foo");
    }

    public function testAddExistingFile()
    {
        $this->assertInstanceOf(
            get_class($this->loadedZip),
            $this->loadedZip->addFile(
                __DIR__ . DS . "TestResources/test_file",
                "/f/f/"
            )
        );

        /**
         * Show that even weird paths are correctly interpreted
         */
        $this->assertTrue(
            $this->loadedZip->hasFile("///f//f/test_file/")
        );
    }

    public function testAddExistingFileInNewDirectory()
    {
        $this->assertInstanceOf(
            get_class($this->loadedZip),
            $this->loadedZip->addFile(
                __DIR__ . DS . "TestResources/test_file",
                "testDir"
            )
        );

        /**
         * You can create and check the directory and files
         * without giving the initial and final slashes
         */
        $this->assertTrue(
            $this->loadedZip->hasDirectory("testDir")
        );

        $this->assertTrue(
            $this->loadedZip->hasDirectory("/testDir")
        );

        $this->assertTrue(
            $this->loadedZip->hasDirectory("testDir/")
        );

        $this->assertTrue(
            $this->loadedZip->hasDirectory("/testDir/")
        );

        $this->assertTrue(
            $this->loadedZip->hasFile("testDir/test_file")
        );

        $this->assertTrue(
            $this->loadedZip->hasFile("/testDir/test_file")
        );

        $this->assertTrue(
            $this->loadedZip->hasFile("testDir/test_file/")
        );

        $this->assertTrue(
            $this->loadedZip->hasFile("/testDir/test_file/")
        );
    }

    public function testBuildArchiveResponse()
    {
        $loadedArchiveResponse = $this->loadedZip
<<<<<<< HEAD
<<<<<<< HEAD
            ->buildArchiveResponse("test")
=======
            ->buildArchiveResponse()
>>>>>>> Define archive builders and formatters
=======
            ->buildArchiveResponse("test")
>>>>>>> Fix Test
        ;

        $loadedArchiveResponseContent = $loadedArchiveResponse->getContent();

        $content = file_get_contents(__DIR__ . DS . "TestResources/well_formatted.zip");

        $this->assertEquals(
            $content,
            $loadedArchiveResponseContent
        );
    }
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> Finish implementing and testing zip

    public function testAddValidFileFromString()
    {
        $this->loadedZip->addFileFromString(
            "foo", "bar"
        );

        $this->assertTrue(
            $this->loadedZip->hasFile("bar")
        );

        $this->assertEquals(
            "foo",
            $this->loadedZip->getFileContent("bar")
        );
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> Complete zip tests

        $this->loadedZip->addFileFromString(
            "foo", "bar", "baz"
        );

        $this->assertTrue(
            $this->loadedZip->hasFile("baz/bar")
        );

        $this->assertEquals(
            "foo",
            $this->loadedZip->getFileContent("baz/bar")
        );
<<<<<<< HEAD
=======
>>>>>>> Finish implementing and testing zip
=======
>>>>>>> Complete zip tests
    }

    /**
     * @expectedException \Thelia\Exception\FileNotFoundException
     */
    public function testGetFileContentFileNotFound()
    {
        $this->loadedZip->getFileContent("bar");
    }

    /**
     * @expectedException \ErrorException
     */
    public function testAddNotValidFileFromString()
    {
        $this->loadedZip->addFileFromString(
            "foo", $this
        );
    }

    /**
     * @expectedException \ErrorException
     */
    public function testAddNotValidFileValueFromString()
    {
        $this->loadedZip->addFileFromString(
            $this, "bar"
        );
    }

<<<<<<< HEAD
<<<<<<< HEAD
}
=======
} 
>>>>>>> Define archive builders and formatters
=======
} 
>>>>>>> Finish implementing and testing zip
=======
}
>>>>>>> Fix cs
