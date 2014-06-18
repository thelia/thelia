<?php

namespace Thelia\Model;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Router;
use Thelia\Model\Base\ProductDocument as BaseProductDocument;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Breadcrumb\BreadcrumbInterface;
use Thelia\Model\Breadcrumb\CatalogBreadcrumbTrait;
use Thelia\Model\Tools\PositionManagementTrait;
use Thelia\Model\Tools\ModelEventDispatcherTrait;

class ProductDocument extends BaseProductDocument implements BreadcrumbInterface
{
    use ModelEventDispatcherTrait;
    use PositionManagementTrait;
    use CatalogBreadcrumbTrait;

    /**
     * Calculate next position relative to our parent
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByProduct($this->getProduct());
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
     * Set Document parent id
     *
     * @param int $parentId parent id
     *
     * @return $this
     */
    public function setParentId($parentId)
    {
        $this->setProductId($parentId);

        return $this;
    }

    /**
     * Get Document parent id
     *
     * @return int parent id
     */
    public function getParentId()
    {
        return $this->getProductId();
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        $this->reorderBeforeDelete(
            array(
                "product_id" => $this->getProductId(),
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
        return $this->getProductBreadcrumb($router, $container, $tab);
    }
}
