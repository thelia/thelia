<?php

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
 * @package Thelia\Core\Event\Document
 * @author  Franck Allimant <franck@cqfdev.fr>
 *
 */
class FileCreateOrUpdateEvent extends ActionEvent
{
    /** @var FileModelInterface model to save */
    protected $model = [];

    /** @var FileModelInterface model to save */
    protected $oldModel = [];

    /** @var UploadedFile Document file to save */
    protected $uploadedFile;

    /** @var int Document parent id */
    protected $parentId;

    /** @var string Parent name */
    protected $parentName;

    /**
     * Constructor
     *
     * @param int $parentId file parent id
     */
    public function __construct($parentId)
    {
        $this->parentId  = $parentId;
    }

    /**
     * Set file to save
     *
     * @param FileModelInterface $model Document to save
     *
     * @return $this
     */
    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Get file being saved
     *
     * @return FileModelInterface
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Set Document parent id
     *
     * @param int $parentId Document parent id
     *
     * @return $this
     */
    public function setParentId($parentId)
    {
        $this->parentId = $parentId;

        return $this;
    }

    /**
     * Get Document parent id
     *
     * @return int
     */
    public function getParentId()
    {
        return $this->parentId;
    }

    /**
     * Set uploaded file
     *
     * @param UploadedFile|null $uploadedFile File being uploaded
     *
     * @return $this
     */
    public function setUploadedFile($uploadedFile)
    {
        $this->uploadedFile = $uploadedFile;

        return $this;
    }

    /**
     * Get uploaded file
     *
     * @return UploadedFile|null
     */
    public function getUploadedFile()
    {
        return $this->uploadedFile;
    }

    /**
     * Set parent name
     *
     * @param string $parentName Parent name
     *
     * @return $this
     */
    public function setParentName($parentName)
    {
        $this->parentName = $parentName;

        return $this;
    }

    /**
     * Get parent name
     *
     * @return string
     */
    public function getParentName()
    {
        return $this->parentName;
    }

    /**
     * Set old model value
     *
     * @param FileModelInterface $oldModel
     */
    public function setOldModel($oldModel)
    {
        $this->oldModel = $oldModel;
    }

    /**
     * Get old model value
     *
     * @return FileModelInterface
     */
    public function getOldModel()
    {
        return $this->oldModel;
    }
}
