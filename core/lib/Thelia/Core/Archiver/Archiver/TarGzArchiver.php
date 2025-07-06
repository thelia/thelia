<?php

declare(strict_types=1);

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

    public function getId(): string
    {
        return 'thelia.tar.gz';
    }

    public function getName(): string
    {
        return 'Gz';
    }

    public function getExtension(): string
    {
        return 'tgz';
    }

    public function getMimeType(): string
    {
        return 'application/x-gzip';
    }

    public function isAvailable(): bool
    {
        return parent::isAvailable() && \extension_loaded('zlib');
    }
}
