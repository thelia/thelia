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

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Translator;
use Thelia\Exception\FileNotFoundException;

/**
 * Class FileDownloader.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
interface FileDownloaderInterface
{
    /**
     * @param string $url
     * @param string $pathToStore
     *
     * @throws FileNotFoundException
     * @throws \ErrorException
     * @throws \HttpUrlException
     *
     * Downloads the file $url in $pathToStore
     */
    public function download($url, $pathToStore);

    public function __construct(LoggerInterface $logger, Translator $translator);

    /**
     * @return $this
     *
     * Returns an hydrated instance
     */
    public static function getInstance();
}
