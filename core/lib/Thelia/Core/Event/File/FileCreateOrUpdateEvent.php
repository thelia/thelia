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

namespace Thelia\Core\Event\File;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Event\ActionEvent;
use Thelia\Files\FileModelInterface;

/**
 * Event fired when a file is created or updated.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class FileCreateOrUpdateEvent extends ActionEvent
{
    protected FileModelInterface $model;
    protected FileModelInterface $oldModel;
    protected UploadedFile $uploadedFile;
    protected ?string $parentName = null;

    public function __construct(protected ?int $parentId)
    {
    }

    /**
     * Set file to save.
     *
     * @param FileModelInterface $model Document to save
     *
     * @return $this
     */
    public function setModel(FileModelInterface $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Get file being saved.
     */
    public function getModel(): FileModelInterface
    {
        return $this->model;
    }

    /**
     * Set Document parent id.
     *
     * @param int $parentId Document parent id
     *
     * @return $this
     */
    public function setParentId(?int $parentId): static
    {
        $this->parentId = $parentId;

        return $this;
    }

    /**
     * Get Document parent id.
     */
    public function getParentId(): int
    {
        return $this->parentId;
    }

    /**
     * Set uploaded file.
     *
     * @param UploadedFile|null $uploadedFile File being uploaded
     *
     * @return $this
     */
    public function setUploadedFile(?UploadedFile $uploadedFile): static
    {
        $this->uploadedFile = $uploadedFile;

        return $this;
    }

    /**
     * Get uploaded file.
     */
    public function getUploadedFile(): ?UploadedFile
    {
        return $this->uploadedFile;
    }

    /**
     * Set parent name.
     *
     * @param string $parentName Parent name
     *
     * @return $this
     */
    public function setParentName(?string $parentName): static
    {
        $this->parentName = $parentName;

        return $this;
    }

    /**
     * Get parent name.
     */
    public function getParentName(): string
    {
        return $this->parentName;
    }

    /**
     * Set old model value.
     */
    public function setOldModel(FileModelInterface $oldModel): void
    {
        $this->oldModel = $oldModel;
    }

    /**
     * Get old model value.
     */
    public function getOldModel(): FileModelInterface
    {
        return $this->oldModel;
    }
}
