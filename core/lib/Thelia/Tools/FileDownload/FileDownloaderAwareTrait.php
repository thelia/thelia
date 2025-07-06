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

namespace Thelia\Tools\FileDownload;

/**
 * Trait FileDownloaderAwareTrait.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
trait FileDownloaderAwareTrait
{
    protected FileDownloaderInterface $fileDownloader;

    public function getFileDownloader(): FileDownloaderInterface
    {
        if (!$this->fileDownloader instanceof FileDownloaderInterface) {
            $this->fileDownloader = FileDownloader::getInstance();
        }

        return $this->fileDownloader;
    }

    /**
     * @return $this
     */
    public function setFileDownloader(FileDownloaderInterface $fileDownloader)
    {
        $this->fileDownloader = $fileDownloader;

        return $this;
    }
}
