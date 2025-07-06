<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Archiver;

/**
 * Interface ArchiverInterface.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
interface ArchiverInterface
{
    /**
     * Get archiver identifier.
     *
     * @return string The archiver identifier
     */
    public function getId();

    /**
     * Get archiver name.
     *
     * @return string The archiver name
     */
    public function getName();

    /**
     * Get archiver extension.
     *
     * @return string The archiver extension
     */
    public function getExtension();

    /**
     * Get archiver mime type.
     *
     * @return string The archiver mime type
     */
    public function getMimeType();

    /**
     * Get archiver availability.
     *
     * @return bool Archiver availability
     */
    public function isAvailable();

    /**
     * Get archive path.
     *
     * @return string
     */
    public function getArchivePath();

    /**
     * Set archive path.
     *
     * @return $this Return $this, allow chaining
     */
    public function setArchivePath(string $archivePath);

    /**
     * Create a new archive.
     *
     * @param string $baseName The archive name without extension
     *
     * @return $this Return $this, allow chaining
     */
    public function create(string $baseName);

    /**
     * Open an archive.
     *
     * @param string $path Path to archive
     *
     * @return $this Return $this, allow chaining
     */
    public function open(string $path);

    /**
     * Add directory or file to archive.
     *
     * @return $this Return $this, allow chaining
     */
    public function add(string $path, ?string $pathInArchive = null);

    /**
     * Save archive.
     *
     * @return bool True on success, false otherwise
     */
    public function save(): bool;

    /**
     * Extract archive.
     *
     * @param string $toPath Where to extract
     */
    public function extract(string $toPath): void;
}
