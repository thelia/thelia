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
 * This interface defines the methods that an archive builder must have.
=======
 * This interface defines the methods that an archive creator must have.
>>>>>>> Define archive builders and formatters
 */
interface ArchiveBuilderInterface
{
    /**
<<<<<<< HEAD
     * @param  string                                     $filePath           It is the path to access the file.
     * @param  string                                     $directoryInArchive This is the directory where it will be stored in the archive
     * @param  null|string                                $name               The name of the file in the archive. if it null or empty, it keeps the same name
     * @param  bool                                       $isOnline
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\FileNotReadableException
     * @throws \ErrorException
=======
     * @param string $filePath It is the path to access the file.
     * @param string $directoryInArchive This is the directory where it will be stored in the archive
     * @param null|string $name The name of the file in the archive. if it null or empty, it keeps the same name
     * @param bool $isOnline
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\FileNotReadableException
>>>>>>> Define archive builders and formatters
     *
     * This methods adds a file in the archive.
     * If the file is local, $isOnline must be false,
     * If the file online, $filePath must be an URL.
     */
    public function addFile($filePath, $directoryInArchive = "/", $name = null, $isOnline = false);

    /**
<<<<<<< HEAD
     * @param $content
     * @param $name
     * @param  string          $directoryInArchive
     * @return mixed
     * @throws \ErrorException
     *
     * This method creates a file in the archive with its content
     */
    public function addFileFromString($content, $name, $directoryInArchive = "/");

    /**
     * @param  string                                     $pathToFile
     * @return null|string
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\FileNotReadableException
     * @throws \ErrorException
     *
     * This method returns a file content
     */
    public function getFileContent($pathToFile);
    /**
     * @param $pathInArchive
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \ErrorException
=======
     * @param $pathInArchive
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
>>>>>>> Define archive builders and formatters
     *
     * This method deletes a file in the archive
     */
    public function deleteFile($pathInArchive);

    /**
<<<<<<< HEAD
     * @param $directoryPath
     * @return $this
     * @throws \ErrorException
     *
     * This method creates an empty directory
     */
    public function addDirectory($directoryPath);

    /**
     * @params string $filename
=======
>>>>>>> Define archive builders and formatters
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
     * @param string $pathToArchive
     * @param bool $isOnline
>>>>>>> Define archive builders and formatters
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\HttpUrlException
     *
     * Loads an archive
     */
<<<<<<< HEAD
    public function loadArchive($pathToArchive, $isOnline = false);
=======
    public static function loadArchive($pathToArchive, $environment, $isOnline = false);
>>>>>>> Define archive builders and formatters

    /**
     * @param $pathToFile
     * @return bool
     *
     * Checks if the archive has a file
     */
    public function hasFile($pathToFile);

    /**
<<<<<<< HEAD
     * @param  string $directory
=======
     * @param string $directory
>>>>>>> Define archive builders and formatters
     * @return bool
     *
     * Check if the archive has a directory
     */
    public function hasDirectory($directory);

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
