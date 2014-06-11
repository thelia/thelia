<?php

namespace Thelia\Model;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Router;
use Thelia\Model\Base\FolderImage as BaseFolderImage;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Breadcrumb\BreadcrumbInterface;
use Thelia\Model\Breadcrumb\FolderBreadcrumbTrait;

class FolderImage extends BaseFolderImage implements BreadcrumbInterface
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;
    use \Thelia\Model\Tools\PositionManagementTrait;
    use FolderBreadcrumbTrait;

    /**
     * Calculate next position relative to our parent
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByFolder($this->getFolder());
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
     * Set Image parent id
     *
     * @param int $parentId parent id
     *
     * @return $this
     */
    public function setParentId($parentId)
    {
        $this->setFolderId($parentId);

        return $this;
    }

    /**
     * Get Image parent id
     *
     * @return int parent id
     */
    public function getParentId()
    {
        return $this->getFolderId();
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        $this->reorderBeforeDelete(
            array(
                "folder_id" => $this->getFolderId(),
            )
        );

        return true;
    }

    /**
     *
     * return the complete breadcrumb for a given resource.
     *
     * @return array
     */
    public function getBreadcrumb(Router $router, ContainerInterface $container, $tab)
    {
        return $this->getFolderBreadcrumb($router, $container, $tab);
    }
}
