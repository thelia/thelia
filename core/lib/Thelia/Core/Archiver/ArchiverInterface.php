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

namespace Thelia\Core\Archiver;

/**
 * Interface ArchiverInterface
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
interface ArchiverInterface
{
    /**
     * Get archiver identifier
     *
     * @return string The archiver identifier
     */
    public function getId();

    /**
     * Get archiver name
     *
     * @return string The archiver name
     */
    public function getName();

    /**
     * Get archiver extension
     *
     * @return string The archiver extension
     */
    public function getExtension();

    /**
     * Get archiver mime type
     *
     * @return string The archiver mime type
     */
    public function getMimeType();

    /**
     * Get archiver availability
     *
     * @return boolean Archiver availability
     */
    public function isAvailable();

    /**
     * Get archive path
     *
     * @return string
     */
    public function getArchivePath();

    /**
     * Set archive path
     *
     * @param string $archivePath
     *
     * @return $this Return $this, allow chaining
     */
    public function setArchivePath($archivePath);

    /**
     * Create a new archive
     *
     * @param string $baseName The archive name without extension
     *
     * @return $this Return $this, allow chaining
     */
    public function create($baseName);

    /**
     * Open an archive
     *
     * @param string $path Path to archive
     *
     * @return $this Return $this, allow chaining
     */
    public function open($path);

    /**
     * Add directory or file to archive
     *
     * @param string      $path
     * @param null|string $pathInArchive
     *
     * @return $this Return $this, allow chaining
     */
    public function add($path, $pathInArchive = null);

    /**
     * Save archive
     *
     * @return boolean True on success, false otherwise
     */
    public function save();

    /**
     * Extract archive
     *
     * @param string $toPath Where to extract
     *
     * @return \Symfony\Component\HttpFoundation\File\File
     */
    public function extract($toPath);
}
