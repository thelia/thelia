<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Archiver\Archiver;

/**
 * Class TarGzArchiver.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class TarGzArchiver extends TarArchiver
{
    public const COMPRESSION_METHOD = \Phar::GZ;

    public function getId()
    {
        return 'thelia.tar.gz';
    }

    public function getName()
    {
        return 'Gz';
    }

    public function getExtension()
    {
        return 'tgz';
    }

    public function getMimeType()
    {
        return 'application/x-gzip';
    }

    public function isAvailable()
    {
        return parent::isAvailable() && \extension_loaded('zlib');
    }
}
