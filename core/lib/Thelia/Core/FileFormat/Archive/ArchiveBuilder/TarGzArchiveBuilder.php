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

namespace Thelia\Core\FileFormat\Archive\ArchiveBuilder;

/**
 * Class TarGzArchiveBuilder
 * @package Thelia\Core\FileFormat\Archive\ArchiveBuilder
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TarGzArchiveBuilder extends TarArchiveBuilder
{
    public function getName()
    {
        return "tar.gz";
    }

    public function getMimeType()
    {
        return "application/x-gtar";
    }

    public function getExtension()
    {
        return "tar.gz";
    }

    public function setEnvironment($environment)
    {
        parent::setEnvironment($environment);

        $this->previousFile = $this->cacheFile;

        if ($this->compression != \Phar::GZ) {
            $this->tar = $this->tar->compress(\Phar::BZ2, $this->getExtension());
        }

        $this->compression = \Phar::GZ;

        return $this;
    }

<<<<<<< HEAD
<<<<<<< HEAD
}
=======
} 
>>>>>>> Finish tar, tar.gz, tar.bz2 and tests
=======
}
>>>>>>> Fix cs
