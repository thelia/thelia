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

use Thelia\Core\Archiver\AbstractArchiver;

/**
 * Class ZipArchiver
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ZipArchiver extends AbstractArchiver
{
    public function getId()
    {
        return 'thelia.zip';
    }

    public function getName()
    {
        return 'Zip';
    }

    public function getExtension()
    {
        return 'zip';
    }

    public function getMimeType()
    {
        return 'application/zip';
    }

    public function isAvailable()
    {
        return class_exists('\\ZipArchive');
    }

    public function create($baseName)
    {
        $this->archive = new \ZipArchive;

        $this->archivePath = $baseName . '.' . $this->getExtension();

        $this->archive->open($this->archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        return $this;
    }

    public function open($path)
    {
        $this->archive = new \ZipArchive;

        $this->archivePath = $path;

        $this->archive->open($this->archivePath);

        return $this;
    }

    public function save()
    {
        return $this->close();
    }

    public function close()
    {
        return $this->archive->close();
    }
}
