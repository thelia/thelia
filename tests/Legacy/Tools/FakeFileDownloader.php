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

namespace Thelia\Tests\Tools;

use Thelia\Exception\FileNotFoundException;
use Thelia\Tools\FileDownload\FileDownloader;

/**
 * Class FakeFileDownloader.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class FakeFileDownloader extends FileDownloader
{
    /**
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \ErrorException
     * @throws \HttpUrlException
     *
     * Downloads the file $url in $pathToStore
     */
    public function download($url, $pathToStore): void
    {
        if (!file_exists($url) || !is_readable($url)) {
            throw new FileNotFoundException();
        }

        if (!copy($url, $pathToStore)) {
            throw new \ErrorException();
        }
    }
}
