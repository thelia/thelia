<?php

namespace Thelia\Model;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Model\Base\CategoryImage as BaseCategoryImage;
use Propel\Runtime\Connection\ConnectionInterface;

class CategoryImage extends BaseCategoryImage
{
    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * Calculate next position relative to our parent
     */
    protected function addCriteriaToPositionQuery($query) {
        $query->filterByCategory($this->getCategory());
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        return true;
    }

    /**
     * Get picture absolute path
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     *
     * @return null|string
     */
    public function getAbsolutePath()
    {
        return null === $this->file
            ? null
            : $this->getUploadDir().'/'.$this->file;
    }

    /**
     * Get picture web path
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     *
     * @return null|string
     */
    public function getWebPath()
    {
        return null === $this->file
            ? null
            : $this->getUploadDir().'/'.$this->file;
    }


    /**
     * Get rid of the __DIR__ so it doesn't screw up
     * when displaying uploaded doc/image in the view.
     * @return string
     */
    public function getUploadDir()
    {
        return THELIA_LOCAL_DIR . 'media/images/category';
    }

    /**
     * Get Image parent id
     *
     * @return int parent id
     */
    public function getParentId()
    {
        return $this->getCategoryId();
    }

}
