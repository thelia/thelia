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

namespace Thelia\Core\FileFormat\Archive\ArchiveBuilder;
use Thelia\Core\FileFormat\Archive\AbstractArchiveBuilder;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Thelia;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\FileNotFoundException;
use Thelia\Exception\FileNotReadableException;
use Thelia\Log\Tlog;
use Thelia\Tools\FileDownload\FileDownloaderInterface;

/**
 * Class ZipArchiveBuilder
 * @package Thelia\Core\FileFormat\Archive\ArchiveBuilder
 * @author Benjamin Perche <bperche@openstudio.fr>
 *
 * This class is a driver defined by AbstractArchiveBuilder,
 * it's goal is to manage Zip archives.
 *
 * You can create a new archive by creating a new instance,
 * or load an existing zip with the static method loadArchive.
 */
class ZipArchiveBuilder extends AbstractArchiveBuilder
{
    /**
     * @var \ZipArchive
     */
    protected $zip;

    /**
     * @var string This is the absolute path to the zip file in cache
     */
    protected $zipCacheFile;

    /**
     * @var string This is the path of the cache
     */
    protected $cacheDir;

    public function __construct()
    {
        parent::__construct();

        $this->zip = new \ZipArchive();
    }

    /**
     * On the destruction of the class,
     * remove the temporary file.
     */
    function __destruct()
    {
        if ($this->zip instanceof \ZipArchive) {
            @$this->zip->close();

            if (file_exists($this->zipCacheFile)) {
                unlink($this->zipCacheFile);
            }
        }
    }

    /**
     * @param string $filePath It is the path to access the file.
     * @param string $directoryInArchive This is the directory where it will be stored in the archive
     * @param null|string $name The name of the file in the archive. if it null or empty, it keeps the same name
     * @param bool $isOnline
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\FileNotReadableException
     *
     * This methods adds a file in the archive.
     * If the file is local, $isOnline must be false,
     * If the file online, $filePath must be an URL.
     */
    public function addFile($filePath, $directoryInArchive = null, $name = null, $isOnline = false)
    {
        /**
         * Add empty directory if it doesn't exist
         */
        if (empty($directoryInArchive) || preg_match("#^\/+$#", $directoryInArchive)) {
            $directoryInArchive = "";
        }

        if(!empty($directoryInArchive)) {
            $directoryInArchive = $this->getDirectoryPath($directoryInArchive);

            if (!$this->zip->addEmptyDir($directoryInArchive)) {
                throw new \ErrorException(
                    $this->translator->trans(
                        "The directory %dir has not been created in the archive",
                        [
                            "%dir" => $directoryInArchive
                        ]
                    )
                );
            }
        }

        /**
         * Download the file if it is online
         * If it's local check if the file exists and if it is redable
         */
        if ($isOnline) {
            $fileDownloadCache = $this->cacheDir . DS . "download";

            $this->getFileDownloader()
                ->download($filePath, $fileDownloadCache)
            ;

            $filePath = $fileDownloadCache;
        } else {
            if (!file_exists($filePath)) {
                $this->throwFileNotFound($filePath);
            } else if (!is_readable($filePath)) {
                throw new FileNotReadableException(
                    $this->translator
                        ->trans(
                            "The file %file is not readable",
                            [
                                "%file" => $filePath,
                            ]
                        )
                );
            }
        }

        if (empty($name)) {
            $name = basename($filePath);
        }

        /**
         * Then write the file in the archive and commit the changes
         */

        $destination = $directoryInArchive . $name;

        if (!$this->zip->addFile($filePath,$destination)) {
            $translatedErrorMessage = $this->translator->trans(
                "An error occurred while adding this file to the archive: %file",
                [
                    "%file" => $filePath
                ]
            );

            $this->logger->error($translatedErrorMessage);

            throw new \ErrorException($translatedErrorMessage);
        }

        $this->commit();

        return $this;
    }

    /**
     * @param $pathInArchive
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \ErrorException
     *
     * This method deletes a file in the archive
     */
    public function deleteFile($pathInArchive)
    {
        $pathInArchive = $this->getFilePath($pathInArchive);

        if (!$this->hasFile($pathInArchive)) {
            $this->throwFileNotFound($pathInArchive);
        }

        $deleted = $this->zip->deleteName($pathInArchive);

        if (!$deleted) {
            throw new \ErrorException(
                $this->translator->trans(
                    "The file %file has not been deleted",
                    [
                        "%file" => $pathInArchive,
                    ]
                )
            );
        }

        return $this;
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     *
     * This method return an instance of a Response with the archive as content.
     */
    public function buildArchiveResponse()
    {
        $this->zip->comment = "Generated by Thelia v" . Thelia::THELIA_VERSION;

        $this->commit();

        if (!file_exists($this->zipCacheFile)) {
            $this->throwFileNotFound($this->zipCacheFile);
        }

        if (!is_readable($this->zipCacheFile)) {
            throw new FileNotReadableException(
                $this->translator->trans(
                    "The cache file %file is not readable",
                    [
                        "%file" => $this->zipCacheFile
                    ]
                )
            );
        }

        $content = file_get_contents($this->zipCacheFile);

        $this->zip->close();

        return new Response(
            $content,
            200,
            [
                "Content-Type" => $this->getMimeType()
            ]
        );
    }

    /**
     * @param string $pathToArchive
     * @param string $environment
     * @param bool $isOnline
     * @param FileDownloaderInterface $fileDownloader
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\HttpUrlException
     *
     * Loads an archive
     */
    public static function loadArchive(
        $pathToArchive,
        $environment,
        $isOnline = false,
        FileDownloaderInterface $fileDownloader = null
    ) {
        /** @var ZipArchiveBuilder $instance */
        $instance = new static();

        $instance->setEnvironment($environment);
        $zip = $instance->getRawZipArchive();
        $zip->close();

        if ($fileDownloader !== null) {
            $instance->setFileDownloader($fileDownloader);
        }

        if ($isOnline) {
            /**
             * It's an online file
             */
            $instance->getFileDownloader()
                ->download($pathToArchive, $instance->getZipCacheFile())
            ;
        } else {
            /**
             * It's a local file
             */
            if (!is_file($pathToArchive) || !is_readable($pathToArchive)) {
                $instance->throwFileNotFound($pathToArchive);
            }

            if (!copy($pathToArchive, $instance->getZipCacheFile())) {
                $translatedErrorMessage = $instance->getTranslator()->trans(
                    "An unknown error happend while copying %prev to %dest",
                    [
                        "%prev" => $pathToArchive,
                        "%dest" => $instance->getZipCacheFile(),
                    ]
                );

                $instance->getLogger()
                    ->error($translatedErrorMessage)
                ;

                throw new \ErrorException($translatedErrorMessage);
            }
        }

        if (true !== $return = $zip->open($instance->getZipCacheFile())) {
            throw new ZipArchiveException(
                $instance->getZipErrorMessage($return)
            );
        }

        return $instance;
    }

    /**
     * @param $pathToFile
     * @return bool
     *
     * Checks if the archive has a file
     */
    public function hasFile($pathToFile)
    {
        return $this->zip
            ->locateName($this->getFilePath($pathToFile)) !== false
        ;
    }

    /**
     * @param string $directory
     * @return bool
     *
     * Checks if the link $directory exists and if it's not a file.
     */
    public function hasDirectory($directory)
    {
        $link = $this->zip->locateName($this->getDirectoryPath($directory));

        return  $link !== false;
    }

    /**
     * @param string $environment
     * @return $this
     *
     * Sets the execution environment of the Kernel,
     * used to know which cache is used.
     */
    public function setEnvironment($environment)
    {

        $cacheFileName = md5 (uniqid());

        $cacheFile  = $this->getArchiveBuilderCacheDirectory($environment) . DS;
        $cacheFile .= $cacheFileName . "." . $this->getExtension();

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        $opening = $this->zip->open(
            $cacheFile,
            \ZipArchive::CREATE
        );

        if($opening !== true) {
            throw new \ErrorException(
                $this->translator->trans(
                    "An unknown error append"
                )
            );
        }

        $this->zipCacheFile = $cacheFile;

        return $this;
    }

    /**
     * @param $errorCode
     * @return string
     *
     * Give the error message of a \ZipArchive error code
     */
    public function getZipErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case \ZipArchive::ER_EXISTS:
                $message = "The archive already exists";
                break;

            case \ZipArchive::ER_INCONS:
                $message = "The archive is inconsistent";
                break;

            case \ZipArchive::ER_INVAL:
                $message = "Invalid argument";
                break;

            case \ZipArchive::ER_MEMORY:
                $message = "Memory error";
                break;

            case \ZipArchive::ER_NOENT:
                $message = "The file doesn't exist";
                break;

            case \ZipArchive::ER_NOZIP:
                $message = "The file is not a zip archive";
                break;

            case \ZipArchive::ER_OPEN:
                $message = "The file could not be open";
                break;

            case \ZipArchive::ER_READ:
                $message = "The file could not be read";
                break;

            case \ZipArchive::ER_SEEK:
                $message = "Position error";
                break;

            default:
                $message = "Unknown error on the ZIP archive";
                break;
        }

        $zipMessageHead = $this->translator->trans(
            "Zip Error"
        );

        $message = $this->translator->trans(
            "[%zip_head] " . $message,
            [
                "%zip_head" => $zipMessageHead
            ]
        );

        return $message;
    }

    public function commit()
    {
        $this->zip->close();
        $result = $this->zip->open($this->getZipCacheFile());

        if ($result !== true) {
            throw new \ErrorException(
                $this->translator->trans(
                    "The changes could on the Zip Archive not be commited"
                )
            );
        }

        return $this;
    }

    /**
     * @param string $initialString
     * @return string
     *
     * Gives a valid file path for \ZipArchive
     */
    public function getFilePath($initialString)
    {
        /**
         * Remove the / at the beginning and the end.
         */
        $initialString = trim($initialString, "/");

        /**
         * Remove the double, triple, ... slashes
         */
        $initialString = preg_replace("#\/{2,}#", "/", $initialString);

        if (preg_match("#\/?[^\/]+\/[^/]+\/?#", $initialString)) {
            $initialString = "/" . $initialString;
        }
        return $initialString;
    }

    /**
     * @param string $initialString
     * @return string
     *
     * Gives a valid directory path for \ZipArchive
     */
    public function getDirectoryPath($initialString)
    {
        $initialString = $this->getFilePath($initialString);

        if ($initialString[0] !== "/") {
            $initialString = "/" . $initialString;
        }

        return $initialString . "/";
    }

    public function throwFileNotFound($file)
    {

        throw new FileNotFoundException(
            $this->getTranslator()
                ->trans(
                    "The file %file is missing or is not readable",
                    [
                        "%file" => $file,
                    ]
                )
        );
    }

    /**
     * @return string
     *
     * This method must return a string, the name of the format.
     *
     * example:
     * return "XML";
     */
    public function getName()
    {
        return "ZIP";
    }

    /**
     * @return string
     *
     * This method must return a string, the extension of the file format, without the ".".
     * The string should be lowercase.
     *
     * example:
     * return "xml";
     */
    public function getExtension()
    {
        return "zip";
    }

    /**
     * @return string
     *
     * This method must return a string, the mime type of the file format.
     *
     * example:
     * return "application/json";
     */
    public function getMimeType()
    {
        return "application/zip";
    }

    /**
     * @return \ZipArchive
     */
    public function getRawZipArchive()
    {
        return $this->zip;
    }

    public function getZipCacheFile()
    {
        return $this->zipCacheFile;
    }

} 