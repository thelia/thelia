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

namespace Thelia\Core\File;

use Propel\Runtime\ActiveQuery\ModelCriteria;

interface FileModelInterface
{
    /**
     * Set file parent id.
     *
     * @param int $parentId parent id
     *
     * @return $this
     */
    public function setParentId(int $parentId): static;

    /**
     * Get file parent id.
     *
     * @return int parent id
     */
    public function getParentId(): int;

    /**
     * @return string the file name
     */
    public function getFile(): string;

    /**
     * @param string $file the file name
     */
    public function setFile(string $file);

    /**
     * @return FileModelParentInterface the parent file model
     */
    public function getParentFileModel(): FileModelParentInterface;

    /**
     * Get the ID of the form used to change this object information.
     */
    public function getUpdateFormId(): string;

    /**
     * @return string the path to the upload directory where files are stored, without final slash
     */
    public function getUploadDir(): string;

    /**
     * @return string the URL to redirect to after update from the back-office
     */
    public function getRedirectionUrl(): string;

    /**
     * Get the Query instance for this object.
     */
    public function getQueryInstance(): ModelCriteria;

    /**
     * Save the model object.
     */
    public function save();

    /**
     * Delete the model object.
     */
    public function delete();

    /**
     * Get the model object ID.
     */
    public function getId();

    /**
     * Set the current title.
     *
     * @param string $title the title in the current locale
     */
    public function setTitle(string $title);

    /**
     * Get the current title.
     */
    public function getTitle();

    /**
     * Set the chapo.
     *
     * @param string $chapo the chapo in the current locale
     */
    public function setChapo(string $chapo);

    /**
     * Set the description.
     *
     * @param string $description the description in the current locale
     */
    public function setDescription(string $description);

    /**
     * Set the postscriptum.
     *
     * @param string $postscriptum the postscriptum in the current locale
     */
    public function setPostscriptum(string $postscriptum);

    /**
     * Set the current locale.
     *
     * @param string $locale the locale string
     */
    public function setLocale(string $locale);

    /**
     * Set the current locale.
     *
     * @param bool $visible true if the file is visible, false otherwise
     */
    public function setVisible(bool $visible);
}
