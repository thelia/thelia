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
 * This interface defines the methods that an archive creator must have.
 */
interface ArchiveBuilderInterface
{
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
    public function addFile($filePath, $directoryInArchive = "/", $name = null, $isOnline = false);

    /**
     * @param $pathInArchive
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     *
     * This method deletes a file in the archive
     */
    public function deleteFile($pathInArchive);

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     *
     * This method return an instance of a Response with the archive as content.
     */
    public function buildArchiveResponse();

    /**
     * @param string $pathToArchive
     * @param bool $isOnline
     * @return $this
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \Thelia\Exception\HttpUrlException
     *
     * Loads an archive
     */
    public static function loadArchive($pathToArchive, $environment, $isOnline = false);

    /**
     * @param $pathToFile
     * @return bool
     *
     * Checks if the archive has a file
     */
    public function hasFile($pathToFile);

    /**
     * @param string $directory
     * @return bool
     *
     * Check if the archive has a directory
     */
    public function hasDirectory($directory);

    /**
     * @param string $environment
     * @return $this
     *
     * Sets the execution environment of the Kernel,
     * used to know which cache is used.
     */
    public function setEnvironment($environment);
} 