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

use Thelia\Core\Archiver\AbstractArchiver;

/**
 * Class ZipArchiver.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ZipArchiver extends AbstractArchiver
{
    public function getId(): string
    {
        return 'thelia.zip';
    }

    public function getName(): string
    {
        return 'Zip';
    }

    public function getExtension(): string
    {
        return 'zip';
    }

    public function getMimeType(): string
    {
        return 'application/zip';
    }

    public function isAvailable(): bool
    {
        return class_exists('\\ZipArchive');
    }

    public function create(string $baseName): self
    {
        $this->archive = new \ZipArchive();

        $this->archivePath = $baseName.'.'.$this->getExtension();

        $this->archive->open($this->archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        return $this;
    }

    public function open(string $path): self
    {
        $this->archive = new \ZipArchive();

        $this->archivePath = $path;

        $this->archive->open($this->archivePath);

        return $this;
    }

    public function save(): bool
    {
        return $this->close();
    }

    public function close(): bool
    {
        if (null === $this->archive) {
            return true;
        }

        $closed = $this->archive->close();
        $this->archive = null;

        return $closed;
    }

    /**
     * A zip is written when it is closed: what it was given is dropped first, so a
     * failed export never writes a whole archive only to remove it.
     */
    public function discard(): void
    {
        if (null === $this->archive) {
            return;
        }

        $this->archive->unchangeAll();
        $this->archive->close();
        $this->archive = null;
    }
}
