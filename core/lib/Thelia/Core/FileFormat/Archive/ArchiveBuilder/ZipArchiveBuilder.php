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
use Thelia\Core\FileFormat\Archive\ArchiveBuilder\Exception\ZipArchiveException;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Thelia;
use Thelia\Exception\FileNotReadableException;
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
     * On the destruction of the class,
     * remove the temporary file.
     */
    public function __destruct()
    {
        if ($this->zip instanceof \ZipArchive) {
            @$this->zip->close();

            if (file_exists($this->cacheFile)) {
                @unlink($this->cacheFile);
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
    public function addFile($filePath, $directoryInArchive = null, $name = null, $isOnline = false)
    {
        if (!empty($name)) {
            $directoryInArchive .= DS . dirname($name) ;
        }

        $directoryInArchive = $this->formatDirectoryPath($directoryInArchive);

        /**
         * Add empty directory if it doesn't exist
         */

        if (!empty($directoryInArchive)) {
            $this->addDirectory($directoryInArchive);
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
         * Then write the file in the archive and commit the changes
         */
        $destination = $directoryInArchive . $name;

        if (!$this->zip->addFile($fileDownloadCache, $destination)) {
            $translatedErrorMessage = $this->translator->trans(
                "An error occurred while adding this file to the archive: %file",
                [
                    "%file" => $fileDownloadCache
                ]
            );

            $this->logger->error($translatedErrorMessage);

            // if error delete the cache file
            unlink($fileDownloadCache);

            throw new \ErrorException($translatedErrorMessage);
        }

        $this->commit();

        // Delete the temp file
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
        $directoryInArchive = $this->formatDirectoryPath($directoryInArchive);

        if (!empty($directoryInArchive) && $directoryInArchive !== "/") {
            $this->addDirectory($directoryInArchive);
        }

        if (empty($name) || !is_scalar($name)) {
            throw new \ErrorException(
                $this->translator->trans(
                    "The filename is not correct"
                )
            );
        }

        $filePath = $this->getFilePath($directoryInArchive . DS . $name);

        if (!is_scalar($content)) {
            throw new \ErrorException(
                $this->translator->trans(
                    "The content is not correct"
                )
            );
        }

        if (!$this->zip->addFromString($filePath, $content)) {
            throw new \ErrorException(
                $this->translator->trans(
                    "Unable to write the file %file into the archive",
                    [
                        "%file" => $filePath,
                    ]
                )
            );
        }

        $this->commit();
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
            $this->zip->addEmptyDir($directoryInArchive);
            $this->commit();

            if (!$this->hasDirectory($directoryInArchive)) {
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

        $stream = $this->zip->getStream($pathToFile);
        $content = "";

        while (!feof($stream)) {
            $content .= fread($stream, 2);
        }

        fclose($stream);

        return $content;
    }


    /**
     * @param  string $initialString
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

        if (preg_match("#\/?[^\/]+\/[^\/]+\/?#", $initialString)) {
            $initialString = "/" . $initialString;
        }

        return $initialString;
    }

    /**
     * @param  string $initialString
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
        $pathInArchive = $this->formatFilePath($pathInArchive);

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
    public function buildArchiveResponse($filename)
    {
        $this->zip->comment = "Generated by Thelia v" . Thelia::THELIA_VERSION;

        $this->commit();

        if (!file_exists($this->cacheFile)) {
            $this->throwFileNotFound($this->cacheFile);
        }

        if (!is_readable($this->cacheFile)) {
            throw new FileNotReadableException(
                $this->translator->trans(
                    "The cache file %file is not readable",
                    [
                        "%file" => $this->cacheFile
                    ]
                )
            );
        }

        $content = file_get_contents($this->cacheFile);

        $this->zip->close();

        return new Response(
            $content,
            200,
            [
                "Content-Type" => $this->getMimeType(),
                "Content-Disposition" => "attachment; filename=\"". $filename . "." . $this->getExtension() ."\"",
            ]
        );
    }

    /**
     * @param  string                                  $pathToArchive
     * @param  bool                                    $isOnline
     * @param  FileDownloaderInterface                 $fileDownloader
     * @return ZipArchiveBuilder
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\HttpUrlException
     *
     * Loads an archive
     */
    public function loadArchive($pathToArchive, $isOnline = false)
    {
        $back = $this->zip;
        $this->zip = new \ZipArchive();
        $zip = clone $this;
        $this->zip = $back;

        $zip->setEnvironment($this->environment);

        $zip->copyFile(
            $pathToArchive,
            $zip->getCacheFile(),
            $isOnline
        );

        if (true !== $return = $zip->getRawZipArchive()->open($zip->getCacheFile())) {
            throw new ZipArchiveException(
                $zip->getZipErrorMessage($return)
            );
        }

        return $zip;
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
            ->locateName($this->formatFilePath($pathToFile)) !== false
        ;
    }

    /**
     * @param  string $directory
     * @return bool
     *
     * Checks if the link $directory exists and if it's not a file.
     */
    public function hasDirectory($directory)
    {
        $link = $this->zip->locateName($this->formatDirectoryPath($directory));

        return  $link !== false;
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
        parent::setEnvironment($environment);

        $this->zip = new \ZipArchive();

        $cacheFile = $this->generateCacheFile($environment);

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        $opening = $this->zip->open(
            $cacheFile,
            \ZipArchive::CREATE
        );

        if ($opening !== true) {
            throw new \ErrorException(
                $this->translator->trans(
                    "An unknown error append"
                )
            );
        }

        $this->cacheFile = $cacheFile;

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
        $result = $this->zip->open($this->getCacheFile());

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

        if (preg_match("#\/?[^\/]+\/[^/]+\/?#", $initialString)) {
            $initialString = "/" . $initialString;
        }

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

        if ($initialString !== "" && $initialString[0] !== "/") {
            $initialString = "/" . $initialString;
        }

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

    /**
     * @return bool
     *
     * Returns conditions for archive builder to be available ( loaded libraries )
     */
    public function isAvailable()
    {
        return class_exists('\\ZipArchive');
    }
}
