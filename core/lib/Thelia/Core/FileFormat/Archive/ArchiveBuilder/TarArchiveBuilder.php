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
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Thelia;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\FileNotReadableException;
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

    public function __destruct()
    {
        if ($this->tar instanceof \PharData) {
            if (file_exists($this->cacheFile)) {
                unlink($this->cacheFile);
            }
        }
    }

    /**
     * @param  string                                     $filePath           It is the path to access the file.
     * @param  string                                     $directoryInArchive This is the directory where it will be stored in the archive
     * @param  null|string                                $name               The name of the file in the archive. if it null or empty, it keeps the same name
     * @param  bool                                       $isOnline
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
        if (!empty($name)) {
            $dirName = dirname($name);
            if ($dirName == ".") {
                $dirName = "";
            }
            $directoryInArchive .= '/' . $dirName;
        }

        if (empty($name) || !is_scalar($name)) {
            $name = basename($filePath);
        } else {
            $name = basename($name);
        }

        /**
         * Download the file if it is online
         * If it's local check if the file exists and if it is redable
         */
        $fileDownloadCache = $this->cacheDir . DS . md5(uniqid()) . ".tmp";
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

        /**
         * And clear the download temp file
         */
        unlink($fileDownloadCache);

        return $this;
    }

    /**
     * @param $content
     * @param $name
     * @param  string          $directoryInArchive
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
        } catch (\Exception $e) {
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
            } catch (\Exception $e) {
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
     * @param  string                                     $pathToFile
     * @return null|string
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\FileNotReadableException
     * @throws \ErrorException
     *
     * This method returns a file content
     */
    public function getFileContent($pathToFile)
    {
        $pathToFile = $this->formatFilePath($pathToFile);

        if (!$this->hasFile($pathToFile)) {
            $this->throwFileNotFound($pathToFile);
        }

        /** @var \PharFileInfo $fileInfo*/
        $fileInfo = $this->tar[$pathToFile];

        /** @var \SplFileObject $file */
        $file = $fileInfo->openFile();
        $content = "";

        while (false !== ($char = $file->fgetc())) {
            $content .= $char;
        }

        return $content;
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
    public function buildArchiveResponse($filename)
    {
        if (!is_file($this->cacheFile)) {
            $this->throwFileNotFound($this->cacheFile);
        }

        if (!is_readable($this->cacheFile)) {
            throw new FileNotReadableException(
                $this->translator->trans(
                    "The file %file is not readable",
                    [
                        "%file" => $this->cacheFile
                    ]
                )
            );
        }

        $content = file_get_contents($this->cacheFile);

        return new Response(
            $content,
            200,
            [
                "Content-Type" => $this->getMimeType(),
                "Content-Disposition" => "attachment; filename=\"".$filename . "." . $this->getExtension() ."\"",
            ]
        );
    }

    /**
     * @param  string                                  $pathToArchive
     * @param  string                                  $environment
     * @param  bool                                    $isOnline
     * @param  FileDownloaderInterface                 $fileDownloader
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\HttpUrlException
     * @throws TarArchiveException
     *
     * Loads an archive
     */
    public function loadArchive($pathToArchive, $isOnline = false)
    {
        $tar = clone $this;

        $tar
            ->setCacheFile($tar->generateCacheFile($this->environment))
            ->copyFile($pathToArchive, $tar->getCacheFile(), $isOnline);

        /**
         * This throws TarArchiveBuilderException if
         * the archive is not valid.
         */

        return $tar->setEnvironment($tar->environment);
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

            if ($fileInfo->isFile()) {
                $isFile = true;
            }
            /**
             * Catch the exception to avoid its displaying.
             */
        } catch (\BadMethodCallException $e) {}

        return $isFile;
    }

    /**
     * @param  string $directory
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

            if ($fileInfo->isDir()) {
                $isDir = true;
            }
            /**
             * Catch the exception to avoid its displaying.
             */
        } catch (\BadMethodCallException $e) {}

        return $isDir;
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
        if (empty($environment)) {
            throw new \ErrorException(
                $this->translator->trans(
                    "You must define an environment when you use an archive builder"
                )
            );
        }

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

            $this->compressionEntryPoint();

        } catch (\BadMethodCallException $e) {
            /**
             * This should not happen
             */
            $errorMessage = "You have badly called the method setEnvironment twice for %file";
        } catch (\UnexpectedValueException $e) {
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
        $this->environment = $environment;

        return $this;
    }

    /**
     * @param  string $initialString
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
     * @param  string $initialString
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
        return "tar";
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
        return "tar";
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
        return "application/x-tar";
    }

    protected function compressionEntryPoint()
    {
        /**
         * This method must be overwritten if you want to do some
         * stuff to compress you archive
         */
    }

    public function getCompression()
    {
        return $this->compression;
    }

    public function isAvailable()
    {
        return false === (bool) ini_get("phar.readonly") && class_exists("\\PharData");
    }
}
