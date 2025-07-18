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

namespace Thelia\Core\Archiver;

use Thelia\Core\Translation\Translator;

/**
 * Class AbstractArchiver.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
abstract class AbstractArchiver implements ArchiverInterface
{
    /** @var mixed The archive resource */
    protected mixed $archive;

    /** @var string Path to archive */
    protected string $archivePath;

    public function __construct($checkIsAvailable = false)
    {
        if ($checkIsAvailable && !$this->isAvailable()) {
            throw new \Exception(Translator::getInstance()->trans('The archiver :name is not available. Please install the php extension :extension first.', [':name' => $this->getName(), ':extension' => $this->getExtension()]));
        }
    }

    public function getArchivePath(): string
    {
        return $this->archivePath;
    }

    public function setArchivePath(string $archivePath): self
    {
        $this->archivePath = $archivePath;

        return $this;
    }

    public function add(string $path, ?string $pathInArchive = null): self
    {
        $path = realpath($path);

        if (!file_exists($path)) {
            throw new \RuntimeException('File '.$path." doesn't exists");
        }

        if (null === $pathInArchive) {
            $pathInArchive = basename($path);
        }

        if (is_dir($path)) {
            foreach (new \DirectoryIterator($path) as $dirItem) {
                if ($dirItem->isDot()) {
                    continue;
                }

                $this->add($dirItem->getPathname(), $pathInArchive.DS.$dirItem->getFilename());
            }
        } else {
            $this->archive->addFile($path, $pathInArchive);
        }

        return $this;
    }

    public function extract($toPath = null): void
    {
        $this->archive->extractTo($toPath);
    }
}
