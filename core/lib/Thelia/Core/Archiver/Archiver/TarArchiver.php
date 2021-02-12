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

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Archiver\AbstractArchiver;
use Thelia\Core\Archiver\ArchiverInterface;

/**
 * Class TarArchiver
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class TarArchiver extends AbstractArchiver
{
    /**
     * @var integer Compression method
     */
    public const COMPRESSION_METHOD = \Phar::NONE;

    public function getId()
    {
        return 'thelia.tar';
    }

    public function getName()
    {
        return 'Tar';
    }

    public function getExtension()
    {
        return 'tar';
    }

    public function getMimeType()
    {
        return 'application/x-tar';
    }

    public function isAvailable()
    {
        return class_exists('\\PharData');
    }

    public function create($baseName)
    {
        $this->archivePath = $baseName . '.' . $this->getExtension();

        $this->archive = new \PharData($this->archivePath);

        return $this;
    }

    public function open($path)
    {
        $this->archivePath = $path;

        $this->archive = new \PharData($this->archivePath);

        return $this;
    }

    public function save()
    {
    }
}
