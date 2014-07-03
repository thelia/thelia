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
use Thelia\Core\FileFormat\Archive\ArchiveBuilder\Exception\TarArchiveException;
use Thelia\Core\FileFormat\Archive\ArchiveBuilderInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Tools\FileDownload\FileDownloaderInterface;

/**
 * Class TarArchiveBuilder
 * @package Thelia\Core\FileFormat\Archive\ArchiveBuilder
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TarArchiveBuilder extends AbstractArchiveBuilder
{
    const PHAR_FORMAT = \Phar::TAR;

    /** @var  string */
    protected $environment;

    /** @var null|string */
    protected $compression;

    /** @var  string */
    protected $tarCacheFile;

    /** @var \PharData */
    protected $tar;

    /** @var \Thelia\Core\Translation\Translator  */
    protected $translator;

    /** @var \Thelia\Log\Tlog */
    protected $logger;

    function __construct($compressionType = null)
    {
        $this->translator = Translator::getInstance();
        $this->logger = Tlog::getNewInstance();

        $supportedCompression = [
            "gz",
            "bz2",
            null
        ];

        if (!in_array($compressionType, $supportedCompression)) {
            throw new TarArchiveException(
                $this->translator->trans(
                    "The compression %type is not supported"
                )
            );
        }

        $this->compression = $compressionType;
    }

    /**
    public function __destruct()
    {
        if ($this->tar instanceof \PharData) {
            if (file_exists($this->cacheFile)) {
                unlink($this->cacheFile);
            }
        }
    }*/

    /**
     * @param string $filePath It is the path to access the file.
     * @param string $directoryInArchive This is the directory where it will be stored in the archive
     * @param null|string $name The name of the file in the archive. if it null or empty, it keeps the same name
     * @param bool $isOnline
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\FileNotReadableException
     * @throws \ErrorException
     *
     * This methods adds a file in the archive.
     * If the file is local, $isOnline must be false,
     * If the file online, $filePath must be an URL.
     */
    public function addFile($filePath, $directoryInArchive = "/", $name = null, $isOnline = false)
    {
        if (empty($name) || !is_scalar($name)) {
            $name = basename($filePath);
        }

        /**
         * Download the file if it is online
         * If it's local check if the file exists and if it is redable
         */
        $fileDownloadCache = $this->cacheDir . DS . "download.tmp";
        $this->copyFile($filePath, $fileDownloadCache, $isOnline);

        /**
         * Then write the file in the archive
         */
        $directoryInArchive = $this->formatDirectoryPath($directoryInArchive);

        if (!empty($directoryInArchive)) {
            $name = $this->formatFilePath(
                $directoryInArchive . $name
            );
        }

        $this->tar->addFile($filePath, $name);

        return $this;
    }

    /**
     * @param $content
     * @param $name
     * @param string $directoryInArchive
     * @return mixed
     * @throws \ErrorException
     *
     * This method creates a file in the archive with its content
     */
    public function addFileFromString($content, $name, $directoryInArchive = "/")
    {
        if (empty($name) || !is_scalar($name)) {
            throw new \ErrorException(
                $this->translator->trans(
                    "The file name must be valid"
                )
            );
        }

        $directoryInArchive = $this->formatDirectoryPath($directoryInArchive);

        if (!empty($directoryInArchive)) {
            $name = $this->formatFilePath(
                $directoryInArchive . $name
            );
        }
        try {
            $this->tar->addFromString($name, $content);
        } catch(\Exception $e) {
            throw new \ErrorException(
                $this->translator->trans(
                    "Error while writing the file into the archive, error message: %errmes",
                    [
                        "%errmes" => $e->getMessage()
                    ]
                )
            );
        }
    }


    /**
     * @param $directoryPath
     * @return $this
     * @throws \ErrorException
     *
     * This method creates an empty directory
     */
    public function addDirectory($directoryPath)
    {
        $directoryInArchive = $this->formatDirectoryPath($directoryPath);

        if (!empty($directoryInArchive)) {

            try {
                $this->tar->addEmptyDir($directoryInArchive);
            } catch(\Exception $e) {
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

        return $this;
    }

    /**
     * @param string $pathToFile
     * @return null|string
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\FileNotReadableException
     * @throws \ErrorException
     *
     * This method returns a file content
     */
    public function getFileContent($pathToFile)
    {
        
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
        if (!$this->hasFile($pathInArchive)) {
            $this->throwFileNotFound($pathInArchive);
        }

        if (false === $this->tar->delete($pathInArchive)) {
            throw new \ErrorException(
                $this->translator->trans(
                    "Unknown error while deleting the file %file",
                    [
                        "%file" => $pathInArchive
                    ]
                )
            );
        }
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     *
     * This method return an instance of a Response with the archive as content.
     */
    public function buildArchiveResponse()
    {

    }

    /**
     * @param string $pathToArchive
     * @param string $environment
     * @param bool $isOnline
     * @param FileDownloaderInterface $fileDownloader
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\HttpUrlException
     * @throws TarArchiveException
     *
     * Loads an archive
     */
    public static function loadArchive(
        $pathToArchive,
        $environment,
        $isOnline = false,
        FileDownloaderInterface $fileDownloader = null
    ) {
        /** @var TarArchiveBuilder $instance */
        $instance = new static();

        if ($fileDownloader !== null) {
            $instance->setFileDownloader($fileDownloader);
        }

        $instance->setCacheFile($instance->getCacheFile())
            ->copyFile($pathToArchive, $isOnline);

        /**
         * This throws TarArchiveBuilderException if
         * the archive is not valid.
         */
        $instance->setEnvironment($environment);

        return $instance;
    }

    /**
     * @param $pathToFile
     * @return bool
     *
     * Checks if the archive has a file.
     * In \PharData, if you call it as a array,
     * the keys are the files in the archive.
     */
    public function hasFile($pathToFile)
    {
        $isFile = false;

        $pathToFile = $this->formatFilePath($pathToFile);
        try {
            /** @var \PharFileInfo $fileInfo */
            $fileInfo = $this->tar[$pathToFile];

            if($fileInfo->isFile()) {
                $isFile = true;
            }
            /**
             * Catch the exception to avoid its displaying.
             */
        } catch(\BadMethodCallException $e) {}

        return $isFile;
    }

    /**
     * @param string $directory
     * @return bool
     *
     * Check if the archive has a directory
     */
    public function hasDirectory($directory)
    {
        $isDir = false;

        $pathToDir = $this->formatDirectoryPath($directory);
        try {
            /** @var \PharFileInfo $fileInfo */
            $fileInfo = $this->tar[$pathToDir];

            if($fileInfo->isDir()) {
                $isDir = true;
            }
            /**
             * Catch the exception to avoid its displaying.
             */
        } catch(\BadMethodCallException $e) {}

        return $isDir;
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
        if ($this->cacheFile === null) {
            $cacheFile = $this->generateCacheFile($environment);

            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        } else {
            $cacheFile = $this->cacheFile;
        }

        $errorMessage = null;

        try {
            $this->tar = new \PharData($cacheFile, null, null, static::PHAR_FORMAT);

            switch ($this->compression) {
                case "gz":
                    $this->tar = $this->tar->compress(\Phar::GZ);
                    $cacheFile .= ".gz";
                    break;
                case "bz2":
                    $this->tar = $this->tar->compress(\Phar::BZ2);
                    $cacheFile .= ".bz2";
                    break;
            }

        } catch(\BadMethodCallException $e) {
            /**
             * This should not happen
             */
            $errorMessage = "You have badly called the method setEnvironment twice for %file";
        } catch(\UnexpectedValueException $e) {
            $errorMessage = "The file %file is corrupted";
        }

        if ($errorMessage !== null) {
            throw new TarArchiveException(
                $this->translator->trans(
                    $errorMessage,
                    [
                        "%file" => $cacheFile
                    ]
                )
            );
        }

        $this->cacheFile = $cacheFile;

        return $this;
    }

    /**
     * @param string $initialString
     * @return string
     *
     * Gives a valid file path for \ZipArchive
     */
    public function formatFilePath($initialString)
    {
        /**
         * Remove the / at the beginning and the end.
         */
        $initialString = trim($initialString, "/");

        /**
         * Remove the double, triple, ... slashes
         */
        $initialString = preg_replace("#\/{2,}#", "/", $initialString);

        return $initialString;
    }

    /**
     * @param string $initialString
     * @return string
     *
     * Gives a valid directory path for \ZipArchive
     */
    public function formatDirectoryPath($initialString)
    {
        $initialString = $this->formatFilePath($initialString);

        return $initialString . "/";
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
        $name = "tar";

        if ($this->compression !== null) {
            $name .= "." . $this->compression;
        }

        return $name;
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
        return $this->getName();
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
        return $this->compression === null ?
            "application/x-tar" :
            "application/x-gtar"
        ;
    }

} 