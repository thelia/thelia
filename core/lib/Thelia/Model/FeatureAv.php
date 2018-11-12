<?php

namespace Thelia\Model;

use Thelia\Model\Base\FeatureAv as BaseFeatureAv;
use Thelia\Core\Event\TheliaEvents;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Feature\FeatureAvEvent;

class FeatureAv extends BaseFeatureAv
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * when dealing with position, be sure to work insite the current feature.
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByFeatureId($this->getFeatureId());
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        // Set the current position for the new object
        $this->setPosition($this->getNextPosition());

        $this->dispatchEvent(TheliaEvents::BEFORE_CREATEFEATURE_AV, new FeatureAvEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        parent::postInsert($con);

        $this->dispatchEvent(TheliaEvents::AFTER_CREATEFEATURE_AV, new FeatureAvEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        parent::preUpdate($con);

        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATEFEATURE_AV, new FeatureAvEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        parent::postUpdate($con);

        $this->dispatchEvent(TheliaEvents::AFTER_UPDATEFEATURE_AV, new FeatureAvEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        parent::preDelete($con);

        $this->dispatchEvent(TheliaEvents::BEFORE_DELETEFEATURE_AV, new FeatureAvEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        parent::postDelete($con);

        $this->dispatchEvent(TheliaEvents::AFTER_DELETEFEATURE_AV, new FeatureAvEvent($this));
    }
}
