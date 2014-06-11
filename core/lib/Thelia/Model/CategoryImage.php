<?php

namespace Thelia\Model;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Router;
use Thelia\Model\Base\CategoryImage as BaseCategoryImage;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Breadcrumb\BreadcrumbInterface;
use Thelia\Model\Breadcrumb\CatalogBreadcrumbTrait;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\Model\Tools\PositionManagementTrait;

class CategoryImage extends BaseCategoryImage implements BreadcrumbInterface
{
    use ModelEventDispatcherTrait;
    use PositionManagementTrait;
    use CatalogBreadcrumbTrait;

    /**
     * Calculate next position relative to our parent
     */
    protected function addCriteriaToPositionQuery($query)
    {
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
     * Set Image parent id
     *
     * @param int $parentId parent id
     *
     * @return $this
     */
    public function setParentId($parentId)
    {
        $this->setCategoryId($parentId);

        return $this;
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

    public function preDelete(ConnectionInterface $con = null)
    {
        $this->reorderBeforeDelete(
            array(
                "category_id" => $this->getCategoryId(),
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
        return $this->getCategoryBreadcrumb($router, $container, $tab);
    }
}
