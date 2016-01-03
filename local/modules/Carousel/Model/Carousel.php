<?php

namespace Carousel\Model;

use Carousel\Form\CarouselImageForm;
use Carousel\Model\Base\Carousel as BaseCarousel;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Files\FileModelInterface;
use Thelia\Files\FileModelParentInterface;
use Thelia\Form\BaseForm;

class Carousel extends BaseCarousel implements FileModelInterface
{

    public function preDelete(ConnectionInterface $con = null)
    {
        $carousel = new \Carousel\Carousel();

        $fs = new Filesystem();

        try {
            $fs->remove($carousel->getUploadDir() . DS . $this->getFile());

            return true;
        } catch (IOException $e) {
            return false;
        }
    }

    /**
     * Set file parent id
     *
     * @param int $parentId parent id
     *
     * @return $this
     */
    public function setParentId($parentId)
    {
        return $this;
    }

    /**
     * Get file parent id
     *
     * @return int parent id
     */
    public function getParentId()
    {
        return $this->getId();
    }

    /**
     * @return FileModelParentInterface the parent file model
     */
    public function getParentFileModel()
    {
        return new static;
    }

    /**
     * Get the ID of the form used to change this object information
     *
     * @return BaseForm the form
     */
    public function getUpdateFormId()
    {
        return 'carousel.image';
    }

    /**
     * @return string the path to the upload directory where files are stored, without final slash
     */
    public function getUploadDir()
    {
        $carousel = new \Carousel\Carousel();
        return $carousel->getUploadDir();
    }

    /**
     * @param int $objectId the object ID
     *
     * @return string the URL to redirect to after update from the back-office
     */
    public function getRedirectionUrl()
    {
        return '/admin/module/Carousel';
    }

    /**
     * Get the Query instance for this object
     *
     * @return ModelCriteria
     */
    public function getQueryInstance()
    {
        return CarouselQuery::create();
    }

    /**
     * @param  bool $visible true if the file is visible, false otherwise
     * @return FileModelInterface
     */
    public function setVisible($visible)
    {
        // Not implemented

        return $this;
    }
}
