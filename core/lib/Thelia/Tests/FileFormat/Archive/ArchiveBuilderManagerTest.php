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

namespace Thelia\Tests\FileFormat\Archive;

use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder;
use Thelia\Core\FileFormat\Archive\ArchiveBuilderManager;
use Thelia\Core\Translation\Translator;

/**
 * Class ArchiveBuilderManagerTest
 * @package Thelia\Tests\FileFormat\Archive
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ArchiveBuilderManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ArchiveBuilderManager
     */
    protected $manager;

    public function setUp()
    {
        new Translator(
            new Container()
        );
        $this->manager = new ArchiveBuilderManager("dev");
    }

    public function testAddArchiveBuilder()
    {
        /** @var AbstractArchiveBuilder $instance */
        $instance = $this->getMock(
            "Thelia\\Core\\FileFormat\\Archive\\AbstractArchiveBuilder",
            [
                "isAvailable",
                "getName",
                "getExtension",
                "getMimeType",
                "addFile",
                "addFileFromString",
                "getFileContent",
                "deleteFile",
                "addDirectory",
                "buildArchiveResponse",
                "loadArchive",
                "hasFile",
                "hasDirectory",
            ]
        );

        $instance->expects($this->any())
            ->method("isAvailable")
            ->willReturn(true)
        ;

        $instance->expects($this->any())
            ->method("getName")
            ->willReturn("foo")
        ;

        $this->manager->add($instance);

        $archiveBuilders = $this->manager->getAll();

        $this->assertTrue(
            array_key_exists($instance->getName(), $archiveBuilders)
        );
    }

    public function testDeleteArchiveBuilder()
    {
        /** @var AbstractArchiveBuilder $instance */
        $instance = $this->getMock(
            "Thelia\\Core\\FileFormat\\Archive\\AbstractArchiveBuilder",
            [
                "isAvailable",
                "getName",
                "getExtension",
                "getMimeType",
                "addFile",
                "addFileFromString",
                "getFileContent",
                "deleteFile",
                "addDirectory",
                "buildArchiveResponse",
                "loadArchive",
                "hasFile",
                "hasDirectory",
            ]
        );

        $instance->expects($this->any())
            ->method("isAvailable")
            ->willReturn(true)
        ;

        $instance->expects($this->any())
            ->method("getName")
            ->willReturn("foo")
        ;

        $this->manager->add($instance);

        $this->manager->delete($instance->getName());

        $this->assertTrue(
            count($this->manager->getAll()) === 0
        );
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testDeleteNotExistingArchiveBuilder()
    {
        $this->manager->delete("foo");
    }
}
