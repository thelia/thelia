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

use Thelia\Core\FileFormat\Archive\ArchiveBuilder\TarBz2ArchiveBuilder;

/**
 * Class TarBz2ArchiveBuilderTest
 * @package Thelia\Tests\FileFormat\Archive\ArchiveBuilder
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TarBz2ArchiveBuilderTest extends TarArchiveBuilderTest
{
    protected function getArchiveBuilder()
    {
        return new TarBz2ArchiveBuilder();
    }

    public function testCompression()
    {
        $this->assertEquals(
            \Phar::BZ2,
            $this->tar->getCompression()
        );
    }
}
