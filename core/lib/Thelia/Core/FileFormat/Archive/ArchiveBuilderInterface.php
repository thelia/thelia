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

/**
 * Interface ArchiveBuilderInterface
 * @package Thelia\Core\FileFormat\Archive
 * @author Benjamin Perche <bperche@openstudio.fr>
 *
<<<<<<< HEAD
<<<<<<< HEAD
 * This interface defines the methods that an archive builder must have.
=======
 * This interface defines the methods that an archive creator must have.
>>>>>>> Define archive builders and formatters
=======
 * This interface defines the methods that an archive builder must have.
>>>>>>> Complete zip tests
 */
interface ArchiveBuilderInterface
{
    /**
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> Fix cs
     * @param  string                                     $filePath           It is the path to access the file.
     * @param  string                                     $directoryInArchive This is the directory where it will be stored in the archive
     * @param  null|string                                $name               The name of the file in the archive. if it null or empty, it keeps the same name
     * @param  bool                                       $isOnline
<<<<<<< HEAD
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\FileNotReadableException
     * @throws \ErrorException
=======
     * @param string $filePath It is the path to access the file.
     * @param string $directoryInArchive This is the directory where it will be stored in the archive
     * @param null|string $name The name of the file in the archive. if it null or empty, it keeps the same name
     * @param bool $isOnline
=======
>>>>>>> Fix cs
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\FileNotReadableException
<<<<<<< HEAD
>>>>>>> Define archive builders and formatters
=======
     * @throws \ErrorException
>>>>>>> Finish implementing and testing zip
     *
     * This methods adds a file in the archive.
     * If the file is local, $isOnline must be false,
     * If the file online, $filePath must be an URL.
     */
    public function addFile($filePath, $directoryInArchive = "/", $name = null, $isOnline = false);

    /**
<<<<<<< HEAD
<<<<<<< HEAD
     * @param $content
     * @param $name
     * @param  string          $directoryInArchive
=======
     * @param $content
     * @param $name
<<<<<<< HEAD
     * @param string $directoryInArchive
>>>>>>> Finish implementing and testing zip
=======
     * @param  string          $directoryInArchive
>>>>>>> Fix cs
     * @return mixed
     * @throws \ErrorException
     *
     * This method creates a file in the archive with its content
     */
    public function addFileFromString($content, $name, $directoryInArchive = "/");

    /**
<<<<<<< HEAD
<<<<<<< HEAD
     * @param  string                                     $pathToFile
=======
     * @param string $pathToFile
>>>>>>> Finish implementing and testing zip
=======
     * @param  string                                     $pathToFile
>>>>>>> Fix cs
     * @return null|string
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\FileNotReadableException
     * @throws \ErrorException
     *
     * This method returns a file content
     */
    public function getFileContent($pathToFile);
    /**
<<<<<<< HEAD
     * @param $pathInArchive
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \ErrorException
=======
     * @param $pathInArchive
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
>>>>>>> Define archive builders and formatters
=======
     * @param $pathInArchive
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \ErrorException
>>>>>>> Finish implementing and testing zip
     *
     * This method deletes a file in the archive
     */
    public function deleteFile($pathInArchive);

    /**
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> Finish implementing and testing zip
     * @param $directoryPath
     * @return $this
     * @throws \ErrorException
     *
     * This method creates an empty directory
     */
    public function addDirectory($directoryPath);
<<<<<<< HEAD

    /**
     * @params string $filename
=======
>>>>>>> Define archive builders and formatters
=======
    /**
>>>>>>> Finish implementing and testing zip
     * @return \Thelia\Core\HttpFoundation\Response
     *
     * This method return an instance of a Response with the archive as content.
     */
<<<<<<< HEAD
    public function buildArchiveResponse($filename);

    /**
     * @param  string                                  $pathToArchive
     * @param  bool                                    $isOnline
=======
    public function buildArchiveResponse();

    /**
<<<<<<< HEAD
     * @param string $pathToArchive
     * @param bool $isOnline
<<<<<<< HEAD
<<<<<<< HEAD
>>>>>>> Define archive builders and formatters
=======
     * @param FileDownloaderInterface $fileDownloader
>>>>>>> Finish implementing and testing zip
=======
>>>>>>> Finish tar, tar.gz, tar.bz2 and tests
=======
     * @param  string                                  $pathToArchive
     * @param  bool                                    $isOnline
>>>>>>> Fix cs
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\HttpUrlException
     *
     * Loads an archive
     */
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
    public function loadArchive($pathToArchive, $isOnline = false);
=======
    public static function loadArchive($pathToArchive, $environment, $isOnline = false);
>>>>>>> Define archive builders and formatters
=======
    public static function loadArchive($pathToArchive, $environment, $isOnline = false, FileDownloaderInterface $fileDownloader = null);
>>>>>>> Finish implementing and testing zip
=======
    public function loadArchive($pathToArchive, $isOnline = false);
>>>>>>> Finish tar, tar.gz, tar.bz2 and tests

    /**
     * @param $pathToFile
     * @return bool
     *
     * Checks if the archive has a file
     */
    public function hasFile($pathToFile);

    /**
<<<<<<< HEAD
<<<<<<< HEAD
     * @param  string $directory
=======
     * @param string $directory
>>>>>>> Define archive builders and formatters
=======
     * @param  string $directory
>>>>>>> Fix cs
     * @return bool
     *
     * Check if the archive has a directory
     */
    public function hasDirectory($directory);

<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
}
=======
    /**
     * @param string $environment
     * @return $this
     *
     * Sets the execution environment of the Kernel,
     * used to know which cache is used.
     */
    public function setEnvironment($environment);
} 
>>>>>>> Define archive builders and formatters
=======
} 
>>>>>>> Finish tar, tar.gz, tar.bz2 and tests
=======
}
>>>>>>> Fix cs
