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

namespace Thelia\Core\FileFormat\Archive;
use Thelia\Core\FileFormat\FormatInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Tools\FileDownload\FileDownloaderAwareTrait;

/**
 * Class AbstractArchiveBuilder
 * @package Thelia\Core\FileFormat\Archive
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class AbstractArchiveBuilder implements FormatInterface, ArchiveBuilderInterface
{
    use FileDownloaderAwareTrait;

    const TEMP_DIRECTORY_NAME = "archive_builder";

    /** @var \Thelia\Core\Translation\Translator  */
    protected $translator;

    /** @var \Thelia\Log\Tlog  */
    protected $logger;

    /** @var string */
    protected $cacheDir;

    public function __construct()
    {
        $this->translator = Translator::getInstance();

        $this->logger = Tlog::getNewInstance();
    }

    public function getArchiveBuilderCacheDirectory($environment)
    {
        $theliaCacheDir = THELIA_CACHE_DIR . $environment . DS;

        if (!is_writable($theliaCacheDir)) {
            throw new \ErrorException(
                $this->translator->trans(
                    "The cache directory \"%env\" is not writable",
                    [
                        "%env" => $environment
                    ]
                )
            );
        }

        $archiveBuilderCacheDir = $this->cache_dir = $theliaCacheDir . static::TEMP_DIRECTORY_NAME;

        if (!is_dir($archiveBuilderCacheDir) && !mkdir($archiveBuilderCacheDir, 0755)) {
            throw new \ErrorException(
                $this->translator->trans(
                    "Error while creating the directory \"%directory\"",
                    [
                        "%directory" => static::TEMP_DIRECTORY_NAME
                    ]
                )
            );
        }

        return $archiveBuilderCacheDir;
    }


    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * @return Tlog
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return Translator
     */
    public function getTranslator()
    {
        return $this->translator;
    }
} 