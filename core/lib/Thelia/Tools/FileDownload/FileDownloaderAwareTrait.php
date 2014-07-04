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

namespace Thelia\Tools\FileDownload;

/**
 * Trait FileDownloaderAwareTrait
 * @package Thelia\Tools\FileDownload
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
trait FileDownloaderAwareTrait
{
    /** @var  FileDownloaderInterface */
    protected $fileDownloader;

    /**
     * @return FileDownloaderInterface
     */
    public function getFileDownloader()
    {
        if (!$this->fileDownloader instanceof FileDownloaderInterface) {
            $this->fileDownloader = FileDownloader::getInstance();
        }

        return $this->fileDownloader;
    }

    /**
     * @param  FileDownloaderInterface $fileDownloader
     * @return $this
     */
    public function setFileDownloader(FileDownloaderInterface $fileDownloader)
    {
        $this->fileDownloader = $fileDownloader;

        return $this;
    }
}
