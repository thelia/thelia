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
use Thelia\Core\FileFormat\Archive\ArchiveBuilder\TarGzArchiveBuilder;

/**
 * Class TarGzArchiveBuilderTest
 * @package Thelia\Tests\FileFormat\Archive\ArchiveBuilder
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TarGzArchiveBuilderTest extends TarArchiveBuilderTest
{
    public function setUp()
    {
        parent::setUp();

        $this->tar = new TarGzArchiveBuilder();
        $this->tar->setEnvironment("test");
    }

    public function testCompression()
    {
        $this->assertEquals(
            \Phar::GZ,
            $this->tar->getCompression()
        );
    }
}
