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
 * Class TarArchiver.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class TarArchiver extends AbstractArchiver
{
    /**
     * @var int Compression method
     */
    public const COMPRESSION_METHOD = \Phar::NONE;

    public function getId(): string
    {
        return 'thelia.tar';
    }

    public function getName(): string
    {
        return 'Tar';
    }

    public function getExtension(): string
    {
        return 'tar';
    }

    public function getMimeType(): string
    {
        return 'application/x-tar';
    }

    public function isAvailable(): bool
    {
        return class_exists('\\PharData');
    }

    public function create($baseName): self
    {
        $this->archivePath = $baseName.'.'.$this->getExtension();

        $this->archive = new \PharData($this->archivePath);

        return $this;
    }

    public function open($path): self
    {
        $this->archivePath = $path;

        $this->archive = new \PharData($this->archivePath);

        return $this;
    }

    public function save(): bool
    {
        return true;
    }
}
