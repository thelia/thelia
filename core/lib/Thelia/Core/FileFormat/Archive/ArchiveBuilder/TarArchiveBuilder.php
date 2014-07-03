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
<<<<<<< HEAD
<<<<<<< HEAD
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Thelia;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\FileNotReadableException;
=======
use Thelia\Core\FileFormat\Archive\ArchiveBuilderInterface;
use Thelia\Core\Thelia;
use Thelia\Core\Translation\Translator;
>>>>>>> Finish implementing and testing zip
=======
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Thelia;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\FileNotReadableException;
>>>>>>> Finish Tar archive builder
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

<<<<<<< HEAD
=======
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

<<<<<<< HEAD
    /**
>>>>>>> Finish implementing and testing zip
=======

>>>>>>> Finish Tar archive builder
    public function __destruct()
    {
        if ($this->tar instanceof \PharData) {
            if (file_exists($this->cacheFile)) {
                unlink($this->cacheFile);
            }
        }
<<<<<<< HEAD
<<<<<<< HEAD
    }

    /**
     * @param  string                                     $filePath           It is the path to access the file.
     * @param  string                                     $directoryInArchive This is the directory where it will be stored in the archive
     * @param  null|string                                $name               The name of the file in the archive. if it null or empty, it keeps the same name
     * @param  bool                                       $isOnline
=======
    }*/
=======
    }
>>>>>>> Finish Tar archive builder

    /**
     * @param string $filePath It is the path to access the file.
     * @param string $directoryInArchive This is the directory where it will be stored in the archive
     * @param null|string $name The name of the file in the archive. if it null or empty, it keeps the same name
     * @param bool $isOnline
>>>>>>> Finish implementing and testing zip
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
<<<<<<< HEAD
<<<<<<< HEAD
        $fileDownloadCache = $this->cacheDir . DS . md5(uniqid()) . ".tmp";
=======
        $fileDownloadCache = $this->cacheDir . DS . "download.tmp";
>>>>>>> Finish implementing and testing zip
=======
        $fileDownloadCache = $this->cacheDir . DS . md5(uniqid()) . ".tmp";
>>>>>>> Complete zip tests
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

<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> Finish Tar archive builder
        /**
         * And clear the download temp file
         */
        unlink($fileDownloadCache);

<<<<<<< HEAD
=======
>>>>>>> Finish implementing and testing zip
=======
>>>>>>> Finish Tar archive builder
        return $this;
    }

    /**
     * @param $content
     * @param $name
<<<<<<< HEAD
     * @param  string          $directoryInArchive
=======
     * @param string $directoryInArchive
>>>>>>> Finish implementing and testing zip
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
<<<<<<< HEAD
        } catch (\Exception $e) {
=======
        } catch(\Exception $e) {
>>>>>>> Finish implementing and testing zip
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

<<<<<<< HEAD
=======

>>>>>>> Finish implementing and testing zip
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
<<<<<<< HEAD
            } catch (\Exception $e) {
=======
            } catch(\Exception $e) {
>>>>>>> Finish implementing and testing zip
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
<<<<<<< HEAD
     * @param  string                                     $pathToFile
=======
     * @param string $pathToFile
>>>>>>> Finish implementing and testing zip
     * @return null|string
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\FileNotReadableException
     * @throws \ErrorException
     *
     * This method returns a file content
     */
    public function getFileContent($pathToFile)
    {
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> Fix FileDownloader test
        $pathToFile = $this->formatFilePath($pathToFile);

        if (!$this->hasFile($pathToFile)) {
            $this->throwFileNotFound($pathToFile);
        }

        /** @var \PharFileInfo $fileInfo*/
        $fileInfo = $this->tar[$pathToFile];
<<<<<<< HEAD

        /** @var \SplFileObject $file */
        $file = $fileInfo->openFile();
        $content = "";

=======

        /** @var \SplFileObject $file */
        $file = $fileInfo->openFile();
        $content = "";

>>>>>>> Fix FileDownloader test
        while (false !== ($char = $file->fgetc())) {
            $content .= $char;
        }

        return $content;
    }

=======
        
=======

>>>>>>> Complete zip tests
    }


>>>>>>> Finish implementing and testing zip
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
<<<<<<< HEAD
    public function buildArchiveResponse($filename)
    {
        $this->tar->setMetadata("Generated by Thelia v" . Thelia::THELIA_VERSION);

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
                "Content-Disposition" => $filename . "." . $this->getExtension(),
            ]
        );
    }

    /**
     * @param  string                                  $pathToArchive
     * @param  string                                  $environment
     * @param  bool                                    $isOnline
     * @param  FileDownloaderInterface                 $fileDownloader
=======
    public function buildArchiveResponse()
    {
        $this->tar->setMetadata("Generated by Thelia v" . Thelia::THELIA_VERSION);

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
            ]
        );
    }

    /**
     * @param string $pathToArchive
     * @param string $environment
     * @param bool $isOnline
     * @param FileDownloaderInterface $fileDownloader
>>>>>>> Finish implementing and testing zip
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\HttpUrlException
     * @throws TarArchiveException
     *
     * Loads an archive
     */
<<<<<<< HEAD
    public function loadArchive($pathToArchive, $isOnline = false)
    {
        $tar = clone $this;

        $tar
            ->setCacheFile($tar->generateCacheFile($this->environment))
            ->copyFile($pathToArchive, $tar->getCacheFile(), $isOnline);
=======
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

<<<<<<< HEAD
        $instance->setCacheFile($instance->getCacheFile())
            ->copyFile($pathToArchive, $isOnline);
>>>>>>> Finish implementing and testing zip
=======
        $instance->setCacheFile($instance->generateCacheFile($environment))
            ->copyFile($pathToArchive, $instance->getCacheFile(), $isOnline);
>>>>>>> Fix FileDownloader test

        /**
         * This throws TarArchiveBuilderException if
         * the archive is not valid.
         */
<<<<<<< HEAD

        return $tar->setEnvironment($tar->environment);
=======
        $instance->setEnvironment($environment);

        return $instance;
>>>>>>> Finish implementing and testing zip
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

<<<<<<< HEAD
            if ($fileInfo->isFile()) {
=======
            if($fileInfo->isFile()) {
>>>>>>> Finish implementing and testing zip
                $isFile = true;
            }
            /**
             * Catch the exception to avoid its displaying.
             */
<<<<<<< HEAD
        } catch (\BadMethodCallException $e) {}
=======
        } catch(\BadMethodCallException $e) {}
>>>>>>> Finish implementing and testing zip

        return $isFile;
    }

    /**
<<<<<<< HEAD
     * @param  string $directory
=======
     * @param string $directory
>>>>>>> Finish implementing and testing zip
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

<<<<<<< HEAD
            if ($fileInfo->isDir()) {
=======
            if($fileInfo->isDir()) {
>>>>>>> Finish implementing and testing zip
                $isDir = true;
            }
            /**
             * Catch the exception to avoid its displaying.
             */
<<<<<<< HEAD
        } catch (\BadMethodCallException $e) {}
=======
        } catch(\BadMethodCallException $e) {}
>>>>>>> Finish implementing and testing zip

        return $isDir;
    }

    /**
<<<<<<< HEAD
     * @param  string $environment
=======
     * @param string $environment
>>>>>>> Finish implementing and testing zip
     * @return $this
     *
     * Sets the execution environment of the Kernel,
     * used to know which cache is used.
     */
    public function setEnvironment($environment)
    {
<<<<<<< HEAD
        if (empty($environment)) {
            throw new \ErrorException(
                $this->translator->trans(
                    "You must define an environment when you use an archive builder"
                )
            );
        }

=======
>>>>>>> Finish implementing and testing zip
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

<<<<<<< HEAD
            $this->compressionEntryPoint();

        } catch (\BadMethodCallException $e) {
=======
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
>>>>>>> Finish implementing and testing zip
            /**
             * This should not happen
             */
            $errorMessage = "You have badly called the method setEnvironment twice for %file";
<<<<<<< HEAD
        } catch (\UnexpectedValueException $e) {
=======
        } catch(\UnexpectedValueException $e) {
>>>>>>> Finish implementing and testing zip
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
<<<<<<< HEAD
        $this->environment = $environment;
=======
>>>>>>> Finish implementing and testing zip

        return $this;
    }

    /**
<<<<<<< HEAD
     * @param  string $initialString
=======
     * @param string $initialString
>>>>>>> Finish implementing and testing zip
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
<<<<<<< HEAD
     * @param  string $initialString
=======
     * @param string $initialString
>>>>>>> Finish implementing and testing zip
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
<<<<<<< HEAD
        return "tar";
=======
        $name = "tar";

        if ($this->compression !== null) {
            $name .= "." . $this->compression;
        }

        return $name;
>>>>>>> Finish implementing and testing zip
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
<<<<<<< HEAD
        return "tar";
=======
        return $this->getName();
>>>>>>> Finish implementing and testing zip
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
<<<<<<< HEAD
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
}
=======
        return $this->compression === null ?
            "application/x-tar" :
            "application/x-gtar"
        ;
    }

} 
>>>>>>> Finish implementing and testing zip
