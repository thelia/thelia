<?php

namespace Thelia\Model;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Router;
use Thelia\Model\Base\ContentDocument as BaseContentDocument;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Breadcrumb\BreadcrumbInterface;
use Thelia\Model\Breadcrumb\FolderBreadcrumbTrait;

class ContentDocument extends BaseContentDocument implements BreadcrumbInterface
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;
    use \Thelia\Model\Tools\PositionManagementTrait;
    use FolderBreadcrumbTrait;

    /**
     * Calculate next position relative to our parent
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByContent($this->getContent());
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
        $this->setContentId($parentId);

        return $this;
    }

    /**
     * Get Document parent id
     *
     * @return int parent id
     */
    public function getParentId()
    {
        return $this->getContentId();
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        $this->reorderBeforeDelete(
            array(
                "content_id" => $this->getContentId(),
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
        return $this->getContentBreadcrumb($router, $container, $tab);
    }
}
