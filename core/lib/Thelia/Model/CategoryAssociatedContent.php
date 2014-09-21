<?php

namespace Thelia\Model;

use Thelia\Model\Base\CategoryAssociatedContent as BaseCategoryAssociatedContent;
use Thelia\Core\Event\Category\CategoryAssociatedContentEvent;
use Thelia\Core\Event\TheliaEvents;
use Propel\Runtime\Connection\ConnectionInterface;

class CategoryAssociatedContent extends BaseCategoryAssociatedContent
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_CREATECATEGORY_ASSOCIATED_CONTENT, new CategoryAssociatedContentEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATECATEGORY_ASSOCIATED_CONTENT, new CategoryAssociatedContentEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATECATEGORY_ASSOCIATED_CONTENT, new CategoryAssociatedContentEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATECATEGORY_ASSOCIATED_CONTENT, new CategoryAssociatedContentEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETECATEGORY_ASSOCIATED_CONTENT, new CategoryAssociatedContentEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_DELETECATEGORY_ASSOCIATED_CONTENT, new CategoryAssociatedContentEvent($this));
    }
}
