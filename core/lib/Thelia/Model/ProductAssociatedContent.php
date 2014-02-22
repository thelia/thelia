<?php

namespace Thelia\Model;

use Thelia\Model\Base\ProductAssociatedContent as BaseProductAssociatedContent;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Product\ProductAssociatedContentEvent;
use Thelia\Core\Event\TheliaEvents;

class ProductAssociatedContent extends BaseProductAssociatedContent
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * Calculate next position relative to our product
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByProductId($this->getProductId());
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        $this->dispatchEvent(TheliaEvents::BEFORE_CREATEPRODUCT_ASSOCIATED_CONTENT, new ProductAssociatedContentEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATEPRODUCT_ASSOCIATED_CONTENT, new ProductAssociatedContentEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATEPRODUCT_ASSOCIATED_CONTENT, new ProductAssociatedContentEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATEPRODUCT_ASSOCIATED_CONTENT, new ProductAssociatedContentEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETEPRODUCT_ASSOCIATED_CONTENT, new ProductAssociatedContentEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_DELETEPRODUCT_ASSOCIATED_CONTENT, new ProductAssociatedContentEvent($this));
    }
}
