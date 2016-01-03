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
use Thelia\Core\FileFormat\Archive\ArchiveBuilder\TarArchiveBuilder;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;

/**
 * Class TarArchiveBuilderTest
 * @package Thelia\Tests\FileFormat\Archive\ArchiveBuilder
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TarArchiveBuilderTest extends \PHPUnit_Framework_TestCase
{
    /** @var  TarArchiveBuilder */
    protected $tar;

    protected function getArchiveBuilder()
    {
        return new TarArchiveBuilder();
    }

    public function setUp()
    {
        new Translator(new Container());

        Tlog::getNewInstance();

        $cacheDir = THELIA_CACHE_DIR . "test";
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir);
        }

        $this->tar = $this->getArchiveBuilder();

        if (! $this->tar->isAvailable()) {
            $this->markTestSkipped(
                "The ".$this->tar->getExtension()." archiver can't be tested as its dependencies are not installed/configured in this context"
            );
        }

        $this->tar->setEnvironment("test");
    }

    public function testAddFileAndDirectory()
    {
        /**
         * File
         */
        $tar = $this->tar->addFile(
            __DIR__ . DS . "TestResources/test_file"
        );

        $this->assertTrue($tar->hasFile("test_file"));

        $this->assertFalse($tar->hasDirectory("test_file"));

        $tar = $this->tar->addFile(
            __DIR__ . DS . "TestResources/test_file",
            null,
            "TEST.txt"
        );

        $this->assertTrue($tar->hasFile("TEST.txt"));

        $this->assertFalse($tar->hasDirectory("TEST.txt"));

        /**
         * Directory
         */
        $this->tar->addDirectory("foo");

        $this->assertTrue($tar->hasDirectory("foo"));

        $this->assertFalse($tar->hasFile("foo"));

        /**s
         * File in a directory
         */
        $this->tar->addFile(
            __DIR__ . DS . "TestResources/test_file",
            "bar",
            "baz"
        );

        $this->assertTrue($this->tar->hasFile("bar/baz"));

        $this->assertTrue($this->tar->hasDirectory("bar"));
    }

    public function testAddValidFileFromString()
    {
        $this->tar->addFileFromString(
            "foo",
            "bar"
        );

        $this->assertTrue(
            $this->tar->hasFile("bar")
        );

        $this->assertEquals(
            "foo",
            $this->tar->getFileContent("bar")
        );

        $this->tar->addFileFromString(
            "foo",
            "bar",
            "baz"
        );

        $this->assertTrue(
            $this->tar->hasFile("baz/bar")
        );

        $this->assertEquals(
            "foo",
            $this->tar->getFileContent("baz/bar")
        );
    }

    /**
     * @expectedException \ErrorException
     */
    public function testAddNotValidFileFromString()
    {
        $this->tar->addFileFromString(
            "foo",
            $this
        );
    }

    /**
     * @expectedException \ErrorException
     */
    public function testAddNotValidFileValueFromString()
    {
        $this->tar->addFileFromString(
            $this,
            "foo"
        );
    }

    public function testDeleteFile()
    {
        $this->tar->addFileFromString(
            "foo",
            "bar"
        );

        $this->assertTrue(
            $this->tar->hasFile("bar")
        );

        $this->tar->deleteFile("bar");

        $this->assertFalse(
            $this->tar->hasFile("bar")
        );
    }

    public function testLoadValidArchive()
    {
        $tar = $this->tar->loadArchive(
            __DIR__ . DS . "TestResources/well_formatted.tar"
        );

        $this->assertInstanceOf(
            get_class($this->tar),
            $tar
        );

        $this->assertTrue(
            $tar->hasFile("LICENSE.txt")
        );
    }

    /**
     * @expectedException \Thelia\Core\FileFormat\Archive\ArchiveBuilder\Exception\TarArchiveException
     */
    public function testLoadInvalidArchive()
    {
        $tar = $this->tar->loadArchive(
            __DIR__ . DS . "TestResources/bad_formatted.tar"
        );
    }

    public function testFormatDirectoryPath()
    {
        $this->assertEquals(
            "foo/",
            $this->tar->formatDirectoryPath("foo")
        );

        $this->assertEquals(
            "foo/",
            $this->tar->formatDirectoryPath("/foo")
        );

        $this->assertEquals(
            "foo/",
            $this->tar->formatDirectoryPath("foo/")
        );

        $this->assertEquals(
            "foo/",
            $this->tar->formatDirectoryPath("/foo/")
        );

        $this->assertEquals(
            "foo/bar/",
            $this->tar->formatDirectoryPath("foo/bar")
        );

        $this->assertEquals(
            "foo/bar/",
            $this->tar->formatDirectoryPath("/foo/bar")
        );

        $this->assertEquals(
            "foo/bar/",
            $this->tar->formatDirectoryPath("/foo//bar/")
        );

        $this->assertEquals(
            "foo/bar/",
            $this->tar->formatDirectoryPath("/foo/bar/")
        );

        $this->assertEquals(
            "foo/bar/baz/",
            $this->tar->formatDirectoryPath("foo/bar/baz")
        );

        $this->assertEquals(
            "foo/bar/baz/",
            $this->tar->formatDirectoryPath("//foo/bar///baz/")
        );
    }

    public function testFormatFilePath()
    {
        $this->assertEquals(
            "foo",
            $this->tar->formatFilePath("foo")
        );

        $this->assertEquals(
            "foo",
            $this->tar->formatFilePath("/foo")
        );

        $this->assertEquals(
            "foo",
            $this->tar->formatFilePath("foo/")
        );

        $this->assertEquals(
            "foo",
            $this->tar->formatFilePath("/foo/")
        );

        $this->assertEquals(
            "foo/bar",
            $this->tar->formatFilePath("foo/bar")
        );

        $this->assertEquals(
            "foo/bar",
            $this->tar->formatFilePath("/foo/bar")
        );

        $this->assertEquals(
            "foo/bar",
            $this->tar->formatFilePath("/foo//bar/")
        );

        $this->assertEquals(
            "foo/bar",
            $this->tar->formatFilePath("/foo/bar/")
        );

        $this->assertEquals(
            "foo/bar/baz",
            $this->tar->formatFilePath("foo/bar/baz")
        );

        $this->assertEquals(
            "foo/bar/baz",
            $this->tar->formatFilePath("//foo/bar///baz/")
        );
    }

    public function testCompression()
    {
        $this->assertEquals(
            null,
            $this->tar->getCompression()
        );
    }
}
