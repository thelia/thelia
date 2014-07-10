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
use Thelia\Exception\FileNotFoundException;
use Thelia\Exception\FileNotReadableException;
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

    /** @var  string */
    protected $cacheFile;

    /** @var \Thelia\Core\Translation\Translator  */
    protected $translator;

    /** @var \Thelia\Log\Tlog  */
    protected $logger;

    /** @var string */
    protected $cacheDir;

    /** @var string */
    protected $environment;

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

        $archiveBuilderCacheDir = $this->cacheDir = $theliaCacheDir . static::TEMP_DIRECTORY_NAME;

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

    /**
     * @param $pathToFile
     * @param $destination
     * @param $isOnline
     * @return $this
     * @throws \ErrorException
     */
    public function copyFile($pathToFile, $destination, $isOnline)
    {
        if ($isOnline) {
            /**
             * It's an online file
             */
            $this->getFileDownloader()
                ->download($pathToFile, $destination)
            ;
        } else {
            /**
             * It's a local file
             */
            if (!is_file($pathToFile)) {
                $this->throwFileNotFound($pathToFile);
            } elseif (!is_readable($pathToFile)) {
                throw new FileNotReadableException(
                    $this->translator
                        ->trans(
                            "The file %file is not readable",
                            [
                                "%file" => $pathToFile,
                            ]
                        )
                );
            }

            if (!copy($pathToFile, $destination)) {
                $translatedErrorMessage = $this->translator->trans(
                    "An error happend while copying %prev to %dest",
                    [
                        "%prev" => $pathToFile,
                        "%dest" => $destination,
                    ]
                );

                $this->logger
                    ->error($translatedErrorMessage)
                ;

                throw new \ErrorException($translatedErrorMessage);
            }
        }

        return $this;
    }

    /**
     * @return string
     */
    public function generateCacheFile($environment)
    {
        $cacheFileName = md5(uniqid());

        $cacheFile  = $this->getArchiveBuilderCacheDirectory($environment) . DS;
        $cacheFile .= $cacheFileName . "." . $this->getExtension();

        return $cacheFile;
    }

    public function throwFileNotFound($file)
    {

        throw new FileNotFoundException(
            $this->translator
                ->trans(
                    "The file %file is missing or is not readable",
                    [
                        "%file" => $file,
                    ]
                )
        );
    }

    /**
     * @param $path
     * @return $this
     */
    public function setCacheFile($path)
    {
        $this->cacheFile = $path;

        return $this;
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

    public function getCacheFile()
    {
        return $this->cacheFile;
    }

    /**
     * @param  string $environment
     * @return $this
     *
     * Sets the execution environment of the Kernel,
     * used to know which cache is used.
     */
    public function setEnvironment($environment)
    {
        $this->environment = $environment;
    }
}
