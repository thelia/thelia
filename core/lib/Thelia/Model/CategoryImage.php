<?php

namespace Thelia\Model;

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
     *
     * @return null|string
     */
    public function getAbsolutePath()
    {
        return null === $this->file
            ? null
            : $this->getUploadRootDir().'/'.$this->file;
    }

    /**
     * Get picture web path
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
     * The absolute directory path where uploaded
     * documents should be saved
     * @return string
     */
    protected function getUploadRootDir()
    {
        return __DIR__.'/../../../../../'.$this->getUploadDir();
    }

    /**
     * Get rid of the __DIR__ so it doesn't screw up
     * when displaying uploaded doc/image in the view.
     * @return string
     */
    protected function getUploadDir()
    {
        return 'local/media/images/category';
    }
}
