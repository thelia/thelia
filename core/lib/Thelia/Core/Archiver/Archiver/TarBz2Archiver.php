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
 * Class TarBz2Archiver.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class TarBz2Archiver extends TarArchiver
{
    public const COMPRESSION_METHOD = \Phar::BZ2;

    public function getId()
    {
        return 'thelia.tar.bz2';
    }

    public function getName()
    {
        return 'Bzip2';
    }

    public function getExtension()
    {
        return 'bz2';
    }

    public function getMimeType()
    {
        return 'application/x-bzip2';
    }

    public function isAvailable()
    {
        return parent::isAvailable() && \extension_loaded('bz2');
    }
}
